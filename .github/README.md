<p align="center"><a href="https://forge.sp-tarkov.com" target="_blank"><img src="logo.spt.png" width="400" alt="Single Player Tarkov Logo"></a></p>
<h1 align="center"><em>The Forge</em></h1>
<p align="center">
<a href="https://www.gnu.org/licenses/lgpl-3.0"><img src="https://img.shields.io/badge/License-LGPL_v3-blue.svg" alt="License: LGPL v3"></a>
<a href="https://github.com/sp-tarkov/forge/actions/workflows/security.yml"><img src="https://img.shields.io/github/actions/workflow/status/sp-tarkov/forge/security" alt="GitHub Actions Workflow Status"></a>
<a href="https://github.com/sp-tarkov/forge/actions/workflows/larastan.yml"><img src="https://img.shields.io/github/actions/workflow/status/sp-tarkov/forge/larastan" alt="GitHub Actions Workflow Status"></a>
<a href="https://github.com/sp-tarkov/forge/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/sp-tarkov/forge/tests" alt="GitHub Actions Workflow Status"></a>
<a href="https://github.com/sp-tarkov/forge/commits/develop"><img src="https://img.shields.io/github/commit-activity/m/sp-tarkov/forge" alt="GitHub commit activity"></a>
<a href="https://discord.com/invite/Xn9msqQZan"><img src="https://img.shields.io/discord/875684761291599922" alt="Discord"></a>
</p>

## Development Environment Setup

This is a Laravel project that uses Laravel Sail, which provides a Docker-based development environment. Ensure Docker is installed and running on your machine before proceeding.

### **Clone the Repository:**

```
git clone https://github.com/sp-tarkov/forge.git
cd forge
```

### **Start Laravel Sail:**

Laravel Sail is a light command-line interface for interacting with Laravel's default Docker environment. Start the Docker containers using the following command:

```
vendor/bin/sail up -d
```

### **Accessing the Application:**

Once the Docker containers are running, you can access the application at `http://localhost`.

### **Using Services:**

| Service     | Access Via Application | Access Via Host             |
|-------------|------------------------|-----------------------------|
| MySQL       | `mysql:3306`           | `localhost:3306`            |
| Redis       | `redis:6379`           | `localhost:6379`            |
| Meilisearch | `meilisearch:7700`     | `http://localhost:7700`     |
| Mailpit     | `mailpit:1025`         | `http://localhost:8025`     |
| Horizon     | `N/A`                  | `http://localhost/horizon/` |
| Nova        | `N/A`                  | `http://localhost/nova/`    |
| Pulse       | `N/A`                  | `http://localhost/pulse/`   |

Most of these connection settings should already be configured in the `.env.example` file. Simply save the `.env.example` file as `.env` and adjust further settings as needed.

### Basic Usage Examples

Here are some basic commands to get started with Forge:

#### Migrate and Seed the Database:

The following command will run the database migrations and seed the database with test data:

```
vendor/bin/sail artisan migrate:fresh –seed
```

### More Information

For more information on Laravel development, please refer to the [official documentation](https://laravel.com/docs/11.x/).

## Development Discussion

*__Please note__, we are very early in development and will likely not accept work that is not discussed beforehand through the following channels...*

You may propose new features or improvements of existing Forge behavior in the repository's GitHub discussion board. If you propose a new feature, please be willing to implement at least some of the code that would be needed to complete the feature.

Informal discussion regarding bugs, new features, and implementation of existing features takes place in the `#website-general` channel of the Single Player Tarkov Discord server. Refringe, the maintainer of Forge, is typically present in the channel on weekdays from 9am-5pm Eastern Time (ET), and sporadically present in the channel at other times.

## Which Branch?

The `main` branch is the default branch for Forge. This branch is used for the latest stable release of the site. The `develop` branch is used for the latest development changes. All feature branches should be based on the `develop` branch. All pull requests should target the `develop` branch.

## Security Vulnerabilities

If you discover a security vulnerability within Forge, please email Refringe at me@refringe.com. All security vulnerabilities will be promptly addressed.

## Coding Style

Forge follows the PSR-2 coding standard and the PSR-4 autoloading standard. We use an automated Laravel Pint action to enforce the coding standard, though it's suggested to run your code changes through Pint before contributing.

## Code of Conduct

The Forge code of conduct is derived from the Ruby code of conduct. Any violations of the code of conduct may be reported to Refringe at me@refringe.com.

- Participants will be tolerant of opposing views.
- Participants must ensure that their language and actions are free of personal attacks and disparaging personal remarks.
- When interpreting the words and actions of others, participants should always assume good intentions.
- Behavior that can be reasonably considered harassment will not be tolerated.

