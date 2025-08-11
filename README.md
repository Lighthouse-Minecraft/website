# Getting Started

## Install Project
- Clone repo
- ``mv website lighthouse-website``
- ``cd lighthouse-website``
- Get the FluxUI credentials from FinalAsgard
- ``compoer install``
- ``cp .env.example .env``
- ``php artisan key:generate``
- ``npm install``
- ``php artisan migrate``

## Setup git scripts on your system
- ``ln -s ../../.dev-hooks/pre-commit .git/hooks/pre-commit``
- ``ln -s ../../.dev-hooks/pre-push .git/hooks/pre-push``
- ``chmod +x .dev-hooks/pre-commit``
- ``chmod +x .dev-hooks/pre-push``

## Run Web Server
I recommend running this in a background terminal so you can keep your main terminal available for artisan commands
- ``composer run dev``

To run each component manually instead of the above bundled command:
- ``php artisan serve``
- ``npm run dev`` // This creates a long running process that monitors for changes and refreshes the browser
- ``npm run build`` // This does a one time build of all css/js assets and will need to be run again if those are updated

## Automated Testing
- ``php artisan test`` // Run all tests
- ``php artisan test --filter MyFileTest`` // Run all tests in a single Test file

- ``./vendor/bin/pint --test`` // See what files have lint issues (code guidelines violations)
- ``./vendor/bin/pint --test --dirty`` // See lint issues but only for files you modified

Yes, running ``./vendor/bin/pint`` alone will attempt to fix all issues. Please do not do that. We need to surgically fix files as we go to avoid merge conflicts all over the place. Only modify files you made or you know are not being actively worked by others. This issue should be totally resolved within the next few weeks of development work.
 
