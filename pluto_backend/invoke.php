<?php
if (!array_key_exists("REDIRECT_INVOKE_FILENAME", $_SERVER))
{
	http_response_code(400);
	exit;
}

$code = file_get_contents($_SERVER["REDIRECT_INVOKE_FILENAME"]);

// Redirect STDOUT to something we will be able to read. Needed to make print/io.write from Lua/Pluto work.
$output_fh = tmpfile();
$output_file = stream_get_meta_data($output_fh)["uri"];
$glibc = FFI::cdef(<<<EOC
typedef void FILE;
FILE* stdout;
FILE* freopen(const char* filename, const char* mode, FILE* stream);
int fflush(FILE* stream);
EOC, "libc.so.6");
$glibc->freopen($output_file, "w", $glibc->stdout);

// Load Pluto.
$lib = FFI::cdef(<<<EOC
typedef void lua_State;
typedef int (*lua_CFunction) (lua_State *L);

lua_State *(luaL_newstate) (void);
void (luaL_openlibs) (lua_State *L);
int (luaL_loadbufferx) (lua_State *L, const char *buff, size_t sz, const char *name, const char *mode);
void  (lua_callk) (lua_State *L, int nargs, int nresults, void *ctx, void *k);
void        (lua_pushinteger) (lua_State *L, long long n);
const char *(lua_pushlstring) (lua_State *L, const char *s, size_t len);
void  (lua_pushcclosure) (lua_State *L, lua_CFunction fn, int n);
void  (lua_createtable) (lua_State *L, int narr, int nrec);

void  (lua_settop) (lua_State *L, int idx);

int (lua_getglobal) (lua_State *L, const char *name);

void  (lua_setglobal) (lua_State *L, const char *name);
void  (lua_settable) (lua_State *L, int idx);

const char *(luaL_checklstring) (lua_State *L, int arg, size_t *l);
EOC, __DIR__."/libPluto.so"); // Must be a C ABI build (-DPLUTO_C_LINKAGE)
$nullptr = $lib->cast("void*", 0);

// Create new lua_State
$lua = $lib->luaL_newstate();
$lib->luaL_openlibs($lua);

// Utility functions
if (!function_exists("array_is_list"))
{
	// Added in PHP 8.1.
	function array_is_list(array $array): bool
	{
		$i = 0;
		foreach ($array as $k => $v)
		{
			if ($k !== $i++)
			{
				return false;
			}
		}
		return true;
	}
}

function pop($L, $n)
{
	global $lib;
	$lib->lua_settop($L, -($n)-1);
}

function pushstring($L, $str)
{
	global $lib;
	$lib->lua_pushlstring($L, $str, strlen($str));
}

function push($L, $val)
{
	global $lib;
	if (is_array($val))
	{
		$lib->lua_createtable($L, 0, 0);
		if (array_is_list($val))
		{
			foreach ($val as $k => $v)
			{
				if (push($L, $k + 1))
				{
					if (push($L, $v))
					{
						$lib->lua_settable($L, -3);
					}
					else
					{
						pop($L, 1);
					}
				}
			}
		}
		else
		{
			foreach ($val as $k => $v)
			{
				if (push($L, $k))
				{
					if (push($L, $v))
					{
						$lib->lua_settable($L, -3);
					}
					else
					{
						pop($L, 1);
					}
				}
			}
		}
		return 1;
	}
	else if (is_string($val))
	{
		pushstring($L, $val);
		return 1;
	}
	else if (is_int($val))
	{
		$lib->lua_pushinteger($L, $val);
		return 1;
	}
	return 0;
}

// Push globals
if (push($lua, $_SERVER))
{
	$lib->lua_setglobal($lua, "_SERVER");
}
if (push($lua, $_GET))
{
	$lib->lua_setglobal($lua, "_GET");
}
if (push($lua, $_POST))
{
	$lib->lua_setglobal($lua, "_POST");
}

// Push functions
$lib->lua_pushcclosure($lua, function($L)
{
	global $lib, $nullptr;
	header($lib->luaL_checklstring($L, 1, $nullptr));
}, 0);
$lib->lua_setglobal($lua, "header");

// Run code
$runtime = file_get_contents(__DIR__."/runtime.pluto");
$lib->luaL_loadbufferx($lua, $runtime, strlen($runtime), "pluto-backend runtime", $nullptr);
$lib->lua_callk($lua, 0, 1, $nullptr, $nullptr);
pushstring($lua, $code);
$lib->lua_callk($lua, 1, 0, $nullptr, $nullptr);

// Print whatever was written to STDOUT.
$glibc->fflush($glibc->stdout);
echo file_get_contents($output_file);
