# Getting Started

## Install Project
- Clone repo
- ``mv website lighthouse-website``
- ``cd lighthouse-website``
- Get the FluxUI credentials from FinalAsgard
- ``compoer install``
- ``cp .env.example``
- ``php artisan key:generate``
- ``npm install``
- ``php artisan migrate``

## Setup git scripts on your system
- ``ln -s ../../.dev-hooks/pre-commit .git/hooks/pre-commit``
- ``ln -s ../../.dev-hooks/pre-push .git/hooks/pre-push``
- ``chmod +x .dev-hooks/pre-commit``
- ``chmod +x .dev-hooks/pre-push``

## Run Web Server
I recommend running this in a background terminal so you can keep your main terminal available for artisan commmands
- ``composer run dev``

To run each component manually instead of the above bundled command:
- ``php artisan serve``
- ``npm run dev``

 
