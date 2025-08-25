# Lighthouse Website

## Purpose

This site is the official web portal for Lighthouse Minecraft, designed to provide announcements, user management, and community features for staff and members.

## Goal

The goal of this project is to centralize communication, streamline staff workflows, and provide a secure, user-friendly interface for all community members. Features include:

- Announcements and blogs
- Role and permission management
- Staff tools and dashboards
- Member and staff resources

## Contribution Instructions

All changes must be submitted via pull request to the `develop` branch. Do not commit or merge directly to `develop`. Use feature branches and open a pull request for review and merging. If you need any documentation, please refer to the link in the bottom left corner of the site and navigate to `Staff SOP's -> Web Development`.

Example workflow:

1. Create a new branch from `develop`:

   ```sh
   git checkout develop
   git pull
   git checkout -b feature/my-feature
   ```

2. Make your changes and commit:

   ```sh
   git add .
   git commit -m "Describe your changes"
   ```

3. Push your branch:

   ```sh
   git push origin feature/my-feature
   ```

4. Open a pull request targeting `develop`.

Direct commits or merges to `develop` are not allowed. All code must be reviewed via pull request.

## Code Uniformity

We have yet to establish a comprehensive set of coding standards and guidelines for this project. However, here are some initial recommendations:

- Follow PSR-12 coding standards for PHP.
- Use consistent naming conventions for variables and functions.
- Write clear and concise comments to explain complex logic.
- Keep code DRY (Don't Repeat Yourself) by reusing components and services.

### Namespaces and Imports

For consistency, please use the following import style in your PHP files:

```php
use App\Models\{Announcement};
use Illuminate\Http\{Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Foundation\Auth\Access\{AuthorizesRequests};
```

This import style helps to keep the code clean and organized, making it easier to manage dependencies while also improving readability through grouping related classes together. This approach also helps to avoid long import lists and makes it clear which classes are being used in each file instead of having them scattered throughout the file and unordered, like below:

```php
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\User;
use App\Models\User;
```

## Getting Started

### Install Project

- Clone repo
- ``mv website lighthouse-website``
- ``cd lighthouse-website``
- ``composer install``
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

### Permission Issues

If you encounter permission issues while accessing certain features or resources, please ensure that your user account has the necessary roles and permissions assigned. Here's some ways you can check your permissions:

1. **User Roles**: Verify your assigned roles by checking your user profile in the admin panel. Ensure you have the appropriate role for the action you're trying to perform.

2. **Policy Definitions**: Review the policy definitions in the codebase to understand the permissions required for specific actions. Policies are typically located in the `app/Policies` directory.

3. **Debugging**: You can add debugging statements in the policy methods to log user details and permissions. This can help identify why access is being denied.

    - For example, you can use `dd()` or `Log::info()` to output user information and the specific policy check being performed.
    - You can also use `Log::debug()` to log detailed information during development without interrupting the application flow.
    - You can use `Log::error()` to log error messages when access is denied, including user information and the policy check that failed.
    - You can use `Log::warning()` to log warning messages for potential issues that don't necessarily result in access denial.
    - You can also use `dd()` to dump user information and halt execution for debugging purposes:
        - I recommend creating a function to encapsulate this logging logic so we can call it by a simple command at any time. It could be called `ddUserPermissions()`.

    ```php
    $rank = $user->staff_rank?->name ?? null;
    $department = $user->staff_department?->name ?? null;
    $member = $user->membership_level?->name ?? null;
    $roles = $user->roles()->pluck('name')->toArray();
    $rolesStr = empty($roles) ? null : implode(', ', $roles);

    $msg = "User: {$user->name} (ID: {$user->id}), Rank: " . ($rank ?? 'null') . ", Department: " . ($department ?? 'null') . ", Member: " . ($member ?? 'null') . ", Roles: " . ($rolesStr ?? 'null');

    $missing = [];
    if (!$user->hasRole('Announcement Editor')) {
        $missing[] = 'Announcement Editor role';
    }
    if (!$user->isAtLeastRank(StaffRank::Officer)) {
        $missing[] = 'StaffRank::Officer or higher';
    }
    if (!$user->isAtLeastRank(StaffRank::CrewMember) || !($user->isInDepartment(StaffDepartment::Engineer) || $user->isInDepartment(StaffDepartment::Steward))) {
        $missing[] = 'CrewMember rank in Engineer or Steward department';
    }
    if (!empty($missing)) {
        $msg .= "\nWARNING: Missing permissions: " . implode(', ', $missing) . ".";
    }
    dd($msg);
    ```
