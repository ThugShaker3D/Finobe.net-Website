## Finobe.NET by Bepistown

# [ INFORMATION ]
Happy halloween! and this is our gift to you. Finobe.NET is a really shit revival, and it's been in-development since 2021.

# [ SETUP ]
You need redis.
Run these commands onto terminal: composer install, npm install
rename the .env.example to just .env,configure the .ENV file to your liking.
after you're done setting up the .ENV file enter this into the terminal: php artisan key:generate.
after that do php artisan migrate then go to config/database.php and change this
'database' => env('DB_DATABASE', 'CHANGE THIS TO YOUR DATABASE NAME'),

Use caddy or NGINX to host it.
Have fun!

# [ CREDITS ]
- Original Dev Team - 
Instance - Giving Aesthetiful money to buy things for Finobe.NET
Co-Owner, Developer (The one who made this trainwreck of a revival) - Aesthetiful
Client Developer, Leaker - Karma
~~Client Developer~~ Dude who didn't do anything to contribute to development - WaterBoi

- Bepistown -
watcha27 - rebuilded db
ThugShaker3D - uploaded this to github
Karma - leaking
