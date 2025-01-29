# Contributing

## Development Discussion

*__Please note__, we are very early in development and will likely not accept work that is not discussed beforehand.*

Informal discussion regarding bugs, new features, and implementation of existing features takes place in the `#website-general` channel of the [Single Player Tarkov Discord server](https://discord.com/invite/Xn9msqQZan). Refringe, the maintainer of Forge, is typically present in the channel on weekdays from 9am-5pm Eastern Time (ET), and sporadically present in the channel at other times.

If you propose a new feature, please be willing to implement at least some of the code that would be needed to complete the feature.

## Which Branch?

The `main` branch is the default branch for Forge. This branch is used for the latest stable release of the site. The `develop` branch is used for the latest development changes. All feature branches should be based on the `develop` branch. All pull requests should target the `develop` branch.

## Pull Request Guidelines

- **Keep Them Small**  
  If you're fixing a bug, try to keep the changes to the bug fix only. If you're adding a feature, try to keep the changes to the feature only. This will make it easier to review and merge your changes.
- **Perform a Self-Review**  
  Before submitting your changes, review your own code. This will help you catch any mistakes you may have made.
- **Remove Noise**  
  Remove any unnecessary changes to white space, code style formatting, or some text change that has no impact related to the intention of the PR.
- **Create a Meaningful Title**  
  When creating a PR, make sure the title is meaningful and describes the changes you've made.
- **Write Detailed Commit Messages**  
  Bring out your table manners, speak the Queen's English and be on your best behaviour.

## Style Guide

Forge follows the PSR-2 coding standard and the PSR-4 autoloading standard. We use an automated Laravel Pint action to enforce the coding standard, though it's suggested to run your code changes through Pint before contributing. This can be done by configuring your IDE to format with Pint on save, or manually by running the following command:

```
./vendor/bin/sail pint
```

### Tests

We have a number of tests that are run automatically when you submit a pull request. You can run these tests locally by running `php artisan test`. If you're adding a new feature or fixing a bug, please add tests to cover your changes so that we can ensure they don't break in the future. We use the [Pest PHP testing framework](https://pestphp.com). 
