<p align="center"><a href="https://forge.sp-tarkov.com" target="_blank"><img src="logo.spt.png" width="400" alt="Single Player Tarkov Logo"></a></p>
<h1 align="center"><em>The Forge</em></h1>
<p align="center">
<a href="https://www.mozilla.org/en-US/MPL/2.0/"><img src="https://img.shields.io/badge/License-MPL_2.0-blue.svg" alt="License: MPL 2.0"></a>
<a href="https://github.com/sp-tarkov/forge/actions/workflows/quality.yaml"><img src="https://github.com/sp-tarkov/forge/actions/workflows/quality.yaml/badge.svg" alt="Quality Control Action Status"></a>
<a href="https://github.com/sp-tarkov/forge/actions/workflows/tests.yaml"><img src="https://github.com/sp-tarkov/forge/actions/workflows/tests.yaml/badge.svg" alt="Test Action Status"></a>
<a href="https://discord.com/invite/Xn9msqQZan"><img src="https://img.shields.io/badge/Chat-Discord-5865F2?logo=discord&logoColor=ffffff" alt="Discord Chat"></a>
<a href="https://www.patreon.com/sptarkov"><img src="https://img.shields.io/badge/Fund-Patreon-fe3c71?logo=patreon&logoColor=ffffff" alt="Patreon Fund"></a>
</p>

The Forge is a Laravel-based web application that provides a platform for the Single Player Tarkov community to share and discover user-generated content, such as mods, guides, and other tools. It is currently under heavy development. Please review this entire document before attempting to contribute, especially the "Development Discussion" section.

## Development Environment Setup

We use [Laravel Sail](https://laravel.com/docs/11.x/sail) to mirror the services that are used in our production server in a local development environment. You can see detailed instructions on how to configure the [full development environment](https://dev.sp-tarkov.com/SPT/forge/wiki/Full-Windows-Dev-Env) or a [lightweight development environment](https://dev.sp-tarkov.com/SPT/forge/wiki/Light-Windows-Dev-Env) on the project wiki. The full development environment is recommended.

### Available Services:

| Service     | Access Via Application | Access Via Host  |
|-------------|------------------------|------------------|
| MySQL       | `mysql:3306`           | `localhost:3306` |
| Redis       | `redis:6379`           | `localhost:6379` |
| Meilisearch | `meilisearch:7700`     | `localhost:7700` |
| Mailpit     | `mailpit:1025`         | `localhost:8025` |

### Notable Routes

| Service                          | Authentication | Access Via Host            |
|----------------------------------|----------------|----------------------------|
| Laravel Filament Admin Panel     | Via User Role  | <http://localhost/admin>   |
| Redis Queue Management (Horizon) | Via User Role  | <http://localhost/horizon> |
| Website Status (Pulse)           | Via User Role  | <http://localhost/pulse>   |
| Meilisearch WebUI                | Local Only     | <http://localhost:7700>    |
| Mailpit WebUI                    | Local Only     | <http://localhost:8025>    |

Most of these connection settings should already be configured in the `.env.full` or `.env.light` example files. Simply save one of these (depending on your environment) as `.env` and adjust settings as needed.

### Basic Usage Examples

Here are some basic commands to get started with Forge:

```
# Start the Docker containers in detached mode:
sail up -d
```

```
# View all of the available Artisan commands:
sail artisan
```

```
# Migrate and seed the database with test data:
sail artisan migrate:fresh –seed
```

```
# Run Laravel Horizon (the queue workers/monitor):
sail artisan horizon
```

```
# Sync the local database with the Meilisearch server (requires horizon to be running): 
sail artisan app:search-sync
```

```
# Install NPM dependencies from within the container:
sail npm install
```

```
# Start the development server:
sail npm run dev
```

### More Information

For more information on Laravel development, please refer to the [official documentation](https://laravel.com/docs/11.x/).

## Development Discussion

*__Please note__, we are very early in development and will likely not accept work that is not discussed beforehand.*

Informal discussion regarding bugs, new features, and implementation of existing features takes place in the `#website-general` channel of the [Single Player Tarkov Discord server](https://discord.com/invite/Xn9msqQZan). Refringe, the maintainer of Forge, is typically present in the channel on weekdays from 9am-5pm Eastern Time (ET), and sporadically present in the channel at other times.

If you propose a new feature, please be willing to implement at least some of the code that would be needed to complete the feature.

## Which Branch?

The `main` branch is the default branch for Forge. This branch is used for the latest stable release of the site. The `develop` branch is used for the latest development changes. All feature branches should be based on the `develop` branch. All pull requests should target the `develop` branch.

## Coding Style

Forge follows the PSR-2 coding standard and the PSR-4 autoloading standard. We use an automated Laravel Pint action to enforce the coding standard, though it's suggested to run your code changes through Pint before contributing. This can be done by configuring your IDE to format with Pint on save, or manually by running the following command:

```
./vendor/bin/sail pint
```

## Security Vulnerabilities

If you discover a security vulnerability within Forge, please email Refringe at me@refringe.com. All security vulnerabilities will be promptly addressed.

## Code of Conduct

The Forge development code of conduct is derived from the Ruby code of conduct. Any violations of the code of conduct may be reported to Refringe at me@refringe.com.

- Participants will be tolerant of opposing views.
- Participants must ensure that their language and actions are free of personal attacks and disparaging personal remarks.
- When interpreting the words and actions of others, participants should always assume good intentions.
- Behavior that can be reasonably considered harassment will not be tolerated.
