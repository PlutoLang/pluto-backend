## Apache Configuration

```
<VirtualHost *:80>
	ServerName YOUR_DOMAIN_HERE
	DocumentRoot /var/www/YOUR_DOMAIN_HERE
</VirtualHost>
<Directory /var/www/YOUR_DOMAIN_HERE>
	RewriteEngine on
	RewriteCond %{REQUEST_FILENAME} -f
	RewriteRule ^(.*).pluto$ /pluto_backend/invoke.php [E=INVOKE_FILENAME:%{REQUEST_FILENAME}]
	RewriteCond %{REQUEST_FILENAME} -f
	RewriteRule ^(.*).plutw$ /pluto_backend/invoke.php [E=INVOKE_FILENAME:%{REQUEST_FILENAME}]
	RewriteCond %{REQUEST_FILENAME}\.pluto -f
	RewriteRule ^(.*)$ /pluto_backend/invoke.php [E=INVOKE_FILENAME:%{REQUEST_FILENAME}.pluto]
	RewriteCond %{REQUEST_FILENAME}\.plutw -f
	RewriteRule ^(.*)$ /pluto_backend/invoke.php [E=INVOKE_FILENAME:%{REQUEST_FILENAME}.plutw]
	RewriteCond %{REQUEST_FILENAME}\/index\.pluto -f
	RewriteRule ^(.*)$ /pluto_backend/invoke.php [E=INVOKE_FILENAME:%{REQUEST_FILENAME}/index.pluto]
	RewriteCond %{REQUEST_FILENAME}\/index\.plutw -f
	RewriteRule ^(.*)$ /pluto_backend/invoke.php [E=INVOKE_FILENAME:%{REQUEST_FILENAME}/index.plutw]
</Directory>
```

## What is .plutw?

This is a preliminary file extension to indicate the file should be processed by [pluto-templating-engine](https://github.com/PlutoLang/pluto-templating-engine), allowing for _code_ like this:
```twig
<p>Hello from {{ _SERVER.REQUEST_URI }}</p>
```
