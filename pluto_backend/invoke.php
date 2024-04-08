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

// Load Lua/Pluto and run our code.
$lib = FFI::cdef(<<<EOC
typedef void lua_State;
lua_State *(luaL_newstate) (void);
void (luaL_openlibs) (lua_State *L);
int (luaL_loadstring) (lua_State *L, const char *s);
void  (lua_callk) (lua_State *L, int nargs, int nresults, void *ctx, void *k);
const char *(lua_pushlstring) (lua_State *L, const char *s, size_t len);
void  (lua_createtable) (lua_State *L, int narr, int nrec);

void  (lua_setglobal) (lua_State *L, const char *name);
void  (lua_settable) (lua_State *L, int idx);
EOC, __DIR__."/libPluto.so"); // Must be a C ABI build (-DPLUTO_C_LINKAGE)

$nullptr = $lib->cast("void*", 0);

$lua = $lib->luaL_newstate();
$lib->luaL_openlibs($lua);

function pushstring($L, $str)
{
	global $lib;
	$lib->lua_pushlstring($L, $str, strlen($str));
}

$lib->lua_createtable($lua, 0, 0);
pushstring($lua, "REQUEST_URI");
pushstring($lua, $_SERVER["REQUEST_URI"]);
$lib->lua_settable($lua, -3);
$lib->lua_setglobal($lua, "_SERVER");

if (substr($_SERVER["REDIRECT_INVOKE_FILENAME"], -6) == ".plutw")
{
	$lib->luaL_loadstring($lua, file_get_contents(__DIR__."/plutw-handler.pluto"));
	$lib->lua_callk($lua, 0, 1, $nullptr, $nullptr);
	pushstring($lua, $code);
	$lib->lua_callk($lua, 1, 0, $nullptr, $nullptr);
}
else
{
	$lib->luaL_loadstring($lua, $code);
	$lib->lua_callk($lua, 0, 0, $nullptr, $nullptr);
}

// Print whatever was written to STDOUT.
$glibc->fflush($glibc->stdout);
echo file_get_contents($output_file);
