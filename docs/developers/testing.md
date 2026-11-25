# Code Quality & Testing

## Testing

The full test suite of Pilcrow consists of the following:

* [Backend Unit Testing](#backend-unit-tests)
* [Client Unit Testing](#client-unit-tests)
* [Integration Testing](#integration-tests-e2e)

From the root project directory (`/`), run the following in a command line:
```sh
yarn test
```

This will initiate a synchronous run of all tests.

### Backend Unit Tests

On the PHP side, we use [PHPUnit](https://phpunit.de/) to run backend tests.

To run the PHP unit tests execute the following command **from the `/backend` directory**:
```sh
lando artisan test
```

::: tip Isolating tests from the dev database
`lando artisan test` runs against the same `laravel` database that drives your local UI, so `RefreshDatabase` and `migrate:fresh` will wipe the data you've been working with in the app.

If you'd rather not have the suite clobber that data — useful when iterating tightly (including agent-driven workflows), or when you've spent time setting up specific local state — use the `backend-test` tooling command instead:

```sh
lando backend-test
```

It works independent of the current working directory and points the suite at a dedicated `laravel_testing` database so your dev data stays intact. Note that running PHPUnit directly (e.g. via `lando ssh`) bypasses this override and will still hit the dev database.

Other artisan commands (e.g. `migrate`, `migrate:fresh --seed`, `db:show`) default to the `mysql` connection and will target your dev `laravel` database. Pass `--database=testing` to target `laravel_testing` instead:

```sh
lando artisan migrate:fresh --database=testing --seed
lando artisan db:show --database=testing
```

In practice you rarely need this — `RefreshDatabase` migrates the testing database on demand during each test run — but it's there if you want to inspect or hand-seed it.
:::

::: tip Debugging CI Failures
If tests pass locally but fail in CI, you can reproduce the CI environment using `./scripts/bake-fpm-test.sh`. See [Build System & CI](./build-ci.md) for details.
:::

Be sure to read the [Laravel testing documentation](https://laravel.com/docs/8.x/testing) and [Laravel Lighthouse testing documentation](https://lighthouse-php.com/master/testing/phpunit.html).  Both provide essential information about writing unit tests for our application.

### Client Unit Tests

The client side of the application uses [Vitest](https://vitest.dev), [vue-test-utils](https://vue-test-utils.vuejs.org/) and [@quasar/testing-unit-vitest](https://testing.quasar.dev/packages/unit-vitest/).

To run the client-side unit tests, you can run the following **from the `/client` directory**:

```sh
lando yarn test:unit
```
Each package's documentation is an excellent source of information on testing best practices and examples.  The [Vue Testing Handbook](https://lmiller1990.github.io/vue-testing-handbook/) is also an excellent resource for unit testing a Vue application.

::: tip Debugging CI Failures
To reproduce the CI environment for client tests: `docker buildx bake web-test`
:::

Vitest also has a web interface for browsing and running tests.  To launch it, run:
```sh
lando yarn test:unit:ui
```


### Integration Tests (E2E)

We use [Playwright](https://playwright.dev/) for our integration or end-to-end
testing. Playwright drives a real browser against a running instance of the
application and checks its responses programmatically.

Playwright runs against three browser engines: Chromium, Firefox, and WebKit.
The tests live in `/playwright` and target the Lando-hosted application
under `https://pilcrow.lndo.site` by default.

You can run Playwright two ways: **directly on the host** (using your
system's Node and the Playwright browsers installed locally), or **inside
Lando** via a dedicated container that ships with all three browsers and
their system dependencies preinstalled.

#### Under Lando (recommended)

The Lando path is the easiest way to get going — no host Node version
wrangling, no `playwright install --with-deps` sudo dance, and the browsers
are baked into the image. The Lando integration is shipped as a Lando extra.

Enable it and rebuild:

```sh
lando extras enable playwright
lando rebuild -y
```

This adds a `playwright` service based on
`mcr.microsoft.com/playwright:v1.65.1-noble` and wires up tooling commands.
On first rebuild, Lando runs `yarn install` inside the container.

Then run the suite from anywhere in the project:

```sh
lando playwright-test            # all browsers
lando playwright-test-chromium   # chromium only
lando playwright-test-firefox    # firefox only
lando playwright-test-webkit     # webkit only
lando playwright                 # raw `playwright` CLI passthrough
lando playwright-lint            # ESLint on the suite
lando playwright-typecheck       # tsc --noEmit
lando playwright-validate        # lint + typecheck
```

::: warning Host node_modules vs. container node_modules
If you have previously run Playwright directly on the host, your
`/playwright/node_modules` may contain host-platform binaries that won't
work inside the Lando container. Delete them before enabling the extra:

```sh
rm -rf playwright/node_modules
lando rebuild -y
```
:::

::: tip Headed mode + test runner UI
Both `yarn test:headed` and `yarn test:ui` need a display, so neither
works inside the Lando container. Use the host path for those.
:::

#### Directly on the host

Install the Playwright browsers (one-time setup):

```sh
cd playwright
yarn install
npx playwright install
```

On Linux you may also need system libraries for WebKit:

```sh
sudo npx playwright install-deps webkit
```

Then run the suite **from the `/playwright` directory**:

```sh
yarn test            # all browsers
yarn test:chromium   # chromium only
yarn test:firefox    # firefox only
yarn test:webkit     # webkit only
yarn test:headed     # show browsers
yarn test:report     # open the last HTML report
```

To open the test runner UI in your default browser (handy on WSL where the
native UI can't render):

```sh
yarn test:ui
```

::: tip Debugging on WSL
Under WSLg the Playwright Inspector window opened by `--debug` can appear but
not respond to clicks. Use `yarn test:ui` instead, or `page.pause()` together
with the test runner UI.
:::

#### Test isolation

The suite runs several workers in parallel against one database. To keep them
from interfering, each worker gets its own shadow copy of every table (see
`backend/app/IntegrationTesting/TableSnapshot.php`), and every request the
worker makes is routed to that copy by an `X-Test-Token` header.

The shadow copy is rebuilt from the seeded data before every test. This happens
automatically; a test does not need to ask for it. A test can therefore change
any data it likes without cleaning up afterwards.

The mutations that drive this (`resetDatabase`, `setupTestToken`,
`teardownTestToken`, `seedSubmission`) only exist when `APP_ENV` is `local` or
`testing`.

##### What this requires of backend code

Routing works by changing the table prefix on the database connection for the
length of one request. Laravel applies that prefix for you whenever it builds
the SQL: Eloquent models, `DB::table()`, and the schema builder are all safe.

SQL you write by hand is not prefixed. A table named literally in a raw
statement always refers to the shared, unprefixed table:

```php
// Reaches the shared table, whichever worker asked.
DB::select('select * from users where email = ?', [$email]);
$query->whereRaw('submissions.status = ?', [$status]);

// Follows the worker's prefix.
User::where('email', $email)->get();
DB::table('users')->where('email', $email)->get();
$query->where('submissions.status', $status);
```

Nothing fails when this goes wrong in production, where there is no prefix.
In the test suite one worker starts reading or changing rows that belong to
another, and tests fail now and then, only when run in parallel, in a spec
unrelated to the change. That is hard to trace back, so:

- Prefer Eloquent or the query builder over raw SQL.
- If raw SQL is unavoidable, build table names with `DB::getTablePrefix()`
  rather than writing them out.
- Raw fragments that name no table (`whereRaw('1 = 0')`, expressions over
  column aliases) are fine.

The same applies to anything else that stores state in the database outside
the default connection. A second connection, or the `database` driver for
sessions, cache or queues, would not follow the worker's prefix. Locally and in
CI those use the `file` and `sync` drivers.

#### Writing tests

Import `test` and `expect` from `@fixtures/base`, not from `@playwright/test`.
The base fixtures add:

- `loginAs(email, path)`: log in as a seeded user through the API and open
  `path`. Pass `{ noCache: true }` if the test logs out or changes the
  password, so the session is not reused by a later test.
- `seedSubmission(options)`: create a submission in a given status and get its
  id back. Prefer this over relying on a seeded submission's id.

A few conventions keep the suite reliable:

- Select elements with `page.getByTestId()`, which reads the `data-cy`
  attribute.
- Never wait for a fixed time. To wait for a request, call
  `waitForGQLOperation()` (from `@helpers/graphql`) **before** the action that
  sends the request, and `await` the result after it.
- Check accessibility with `checkA11y(page)` (from `@helpers/a11y`) once the
  page has finished loading, and again after it changes state.

#### In CI

The suite runs on every pull request that touches the client, the backend or
the tests, against the built Docker images and in Chromium only. When a test
fails, the run's `playwright-html-report` artifact contains a trace and a
screenshot for it.

## Code Style & Linting

We use code style checking to help ensure consistency across our codebase.  Style checking is implemented using Github Actions on all pull requests, and each language's style/linting checker can also be run from the local environment.

### PHP

From the root of the project run:
```sh
lando composer lint #Check and report linting errors

lando composer lint-fix #Fix fixable linting errors
```

#### PHPCS in VScode

The [PHP Sniffer](https://marketplace.visualstudio.com/items?itemName=wongjn.php-sniffer) extension should pick up our phpcs config automatically.  You will need to have PHP installed locally, however.

### Markdown

From the root of the project run:
```sh
lando yarn lint:md
```
#### Markdownlint in VSCode

[Markdownlint](https://marketplace.visualstudio.com/items?itemName=DavidAnson.vscode-markdownlint) should pick up our configuration automatically.

### JavaScript

From the `/client` directory run:
```sh
lando yarn lint #Check and report linting errors

lando yarn lint --fix #Fix fixable linting errors
```

### Playwright (TypeScript)

The Playwright suite has its own ESLint config and TypeScript project.
From the `/playwright` directory run:

```sh
yarn lint         # Lint
yarn lint:fix     # Auto-fix lint errors
yarn typecheck    # Typecheck
yarn validate     # Lint + typecheck together
```

Or via the Lando extra (if enabled):

```sh
lando playwright-lint
lando playwright-typecheck
lando playwright-validate
```

#### Eslint in VSCode

Instructions for integrating eslint into VSCode can be found [in the eslint-plugin-vue documentation](https://vuejs.github.io/eslint-plugin-vue/user-guide/#editor-integrations).  You will need to make an additional configuration change to what is outlined there, however:

- Install [dbaeumer.vscode-eslint](https://marketplace.visualstudio.com/items?itemName=dbaeumer.vscode-eslint)
- Edit your VSCode settings.json:

```json
{
  //...
  "eslint.format.enable": true, // Adds eslint to the formatter options in the right-click menu (Optional)
  "eslint.packageManager": "yarn", // Pilcrow uses yarn
  "eslint.workingDirectories": ["./client"], // Point the eslint plugin at the client directory
  "eslint.validate": [
    "javascript",
    "javascriptreact",
    "vue"
  ],
  "vetur.validation.template": false // If you have vetur installed as well, disable the default validation functionality.
  //...
}
```
