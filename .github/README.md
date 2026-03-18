<p align="center"><a href="https://forge.sp-tarkov.com" target="_blank"><img src="logo.spt.png" width="400" alt="Single Player Tarkov Logo"></a></p>
<h1 align="center"><em>The Forge</em></h1>
<p align="center">
<a href="https://www.mozilla.org/en-US/MPL/2.0/"><img src="https://img.shields.io/badge/License-MPL_2.0-blue.svg" alt="License: MPL 2.0"></a>
<a href="https://github.com/sp-tarkov/forge/actions/workflows/tests.yaml"><img src="https://github.com/sp-tarkov/forge/actions/workflows/tests.yaml/badge.svg" alt="Test Action Status"></a>
<a href="https://discord.com/invite/Xn9msqQZan"><img src="https://img.shields.io/badge/Chat-Discord-5865F2?logo=discord&logoColor=ffffff" alt="Discord Chat"></a>
<a href="https://www.patreon.com/sptarkov"><img src="https://img.shields.io/badge/Fund-Patreon-fe3c71?logo=patreon&logoColor=ffffff" alt="Patreon Fund"></a>
</p>

The Forge is a Laravel-based web application that provides a platform for the Single Player Tarkov community to share and discover user-generated content, such as mods, guides, and other tools. It is currently under heavy development. Please review this entire document before attempting to contribute, especially the "Development Discussion" section.

![Alt](https://repobeats.axiom.co/api/embed/622043a870c7a5993d774a4ea1659e6a9898c7cc.svg "Repobeats analytics image")

## Development Environment Setup

We use [Laravel Herd](https://herd.laravel.com) for local development. You can see detailed instructions on how to configure the development environment on the [project wiki](https://github.com/sp-tarkov/forge/wiki).

### Notable Routes

| Service                          | Authentication | Access Via Host            |
|----------------------------------|----------------|----------------------------|
| Redis Queue Management (Horizon) | Via User Role  | `/horizon`                 |
| Website Status (Pulse)           | Via User Role  | `/pulse`                   |

Copy the `.env.example` file to `.env` and adjust settings as needed.

### Basic Usage Examples

Here are some basic commands to get started with Forge:

```
# Start the development server (runs Vite, queue worker, etc.):
composer run dev
```

```
# View all of the available Artisan commands:
php artisan
```

```
# Migrate and seed the database with test data:
php artisan migrate:fresh --seed
```

```
# Run Laravel Horizon (the queue workers/monitor):
php artisan horizon
```

```
# Sync the local database with the Meilisearch server (requires horizon to be running):
php artisan app:search-sync
```

```
# Install NPM dependencies:
npm install
```

```
# Build frontend assets:
npm run build
```

### More Information

For more information on Laravel development, please refer to the [official documentation](https://laravel.com/docs/12.x/).
