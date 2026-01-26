# Pilcrow - Claude Code Reference

## Project Overview

**Pilcrow** is a web application supporting **Collaborative Community Review (CCR)** - a peer-review process for academic publications. It provides infrastructure for transparent, developmental feedback on scholarly work with the goal of improving both the piece and cultivating collegiality among participants.

- **Version**: 0.32.1
- **Repository**: mesh-research/pilcrow
- **License**: MIT
- **Documentation**: https://latest.docs.pilcrow.dev

## Technology Stack

### Backend
| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | 12.0 | PHP Framework |
| PHP | 8.4 | Runtime |
| Lighthouse | 6.0 | GraphQL API |
| MySQL | - | Primary Database |
| Laravel Scout + TNTSearch | - | Full-text Search |
| Laravel Sanctum | 4.0 | Authentication |
| Laravel Socialite | - | OAuth (Google, ORCID) |
| Spatie Permissions | 6.0 | RBAC |
| Laravel Auditing | 14.0 | Change Tracking |
| Pandoc | - | Document Conversion |
| Redis | - | Caching (optional) |

### Frontend
| Technology | Version | Purpose |
|------------|---------|---------|
| Vue.js | 3.0 | Frontend Framework |
| Quasar | 2.6.0 | UI Component Library |
| Apollo Client | 3.7.2 | GraphQL Client |
| Tiptap | 2.0.3 | Rich Text Editor |
| Vue Router | 4.0 | Routing |
| Vue-i18n | 9.2.2 | Internationalization |
| Vuelidate | 2.0 | Form Validation |

### Testing
| Tool | Purpose |
|------|---------|
| PHPUnit | Backend Unit/Feature Tests |
| Vitest | Frontend Unit Tests |
| Cypress | End-to-End Tests |
| cypress-axe | Accessibility Testing |

### Infrastructure
| Tool | Purpose |
|------|---------|
| Docker | Containerization |
| Kubernetes + Helm | Orchestration |
| Lando | Local Development |
| GitHub Actions | CI/CD |

## Directory Structure

```
pilcrow/
├── backend/                    # Laravel PHP backend
│   ├── app/
│   │   ├── Models/            # Eloquent models
│   │   ├── GraphQL/           # Resolvers, mutations, directives
│   │   ├── Policies/          # Authorization policies
│   │   ├── Jobs/              # Queue jobs
│   │   └── ...
│   ├── graphql/               # GraphQL schema files (SDL)
│   ├── config/                # Laravel configuration
│   ├── database/              # Migrations, seeders, factories
│   ├── routes/                # Route definitions
│   ├── tests/                 # PHPUnit tests
│   └── resources/             # Email templates, etc.
│
├── client/                    # Vue 3 + Quasar frontend
│   ├── src/
│   │   ├── pages/            # Route page components
│   │   ├── components/       # Reusable components
│   │   ├── graphql/          # GraphQL queries/mutations
│   │   ├── use/              # Vue composables
│   │   ├── layouts/          # Layout components
│   │   ├── tiptap/           # Rich text editor config
│   │   └── i18n/             # Translations
│   ├── test/                 # Frontend tests
│   └── cypress.config.cjs    # E2E test config
│
├── docs/                      # VitePress documentation
│   ├── guide/                # User guides
│   ├── developers/           # Developer docs
│   └── install/              # Deployment guides
│
├── helm/                      # Kubernetes Helm charts
│
├── .github/workflows/         # CI/CD pipelines
│
├── .lando.yml                # Local dev environment
├── docker-compose.yaml       # Docker Compose config
└── docker-bake.hcl          # Docker BuildKit config
```

## Core Data Models

### Primary Entities

| Model | Description | Location |
|-------|-------------|----------|
| User | User accounts with roles/permissions | `backend/app/Models/User.php` |
| Publication | Collection of submissions for review | `backend/app/Models/Publication.php` |
| Submission | Academic work under review | `backend/app/Models/Submission.php` |
| InlineComment | Comments on specific text passages | `backend/app/Models/InlineComment.php` |
| OverallComment | General feedback comments | `backend/app/Models/OverallComment.php` |
| SubmissionContent | Processed document content | `backend/app/Models/SubmissionContent.php` |

### Submission Workflow States

```
DRAFT (0)
  → INITIALLY_SUBMITTED (1)
    → RESUBMISSION_REQUESTED (2) → RESUBMITTED (3)
    → AWAITING_REVIEW (4)
      → UNDER_REVIEW (8)
        → AWAITING_DECISION (9)
          → REVISION_REQUESTED (10)
          → ACCEPTED_AS_FINAL (6)
          → REJECTED (5)
    → EXPIRED (7)
    → ARCHIVED (11)
    → DELETED (12)
```

### User Roles

**Application-level roles** (defined in `UserRoles` enum):
- `application_admin` (1)
- `publication_admin` (2)
- `editor` (3)
- `review_coordinator` (4)
- `reviewer` (5)
- `submitter` (6)

**Submission-level roles** (defined in `SubmissionUserRoles` enum):
- `review_coordinator` (4)
- `reviewer` (5)
- `submitter` (6)

## GraphQL API

### Schema Location
All GraphQL schema files are in `backend/graphql/`:
- `schema.graphql` - Root schema with Query/Mutation types
- `user.graphql` - User types and enums
- `submission.graphql` - Submission CRUD
- `submission.comments.graphql` - Comment types and mutations
- `publication.graphql` - Publication management
- `notification.graphql` - Notifications
- `permission.graphql` / `role.graphql` - RBAC types
- `settings.graphql` - Application settings
- `oauth.graphql` - OAuth/SSO types
- `audit.graphql` - Audit log types

### Key Directives Used
| Directive | Purpose |
|-----------|---------|
| `@auth` | Requires authenticated user |
| `@guard` | Applies authentication guard |
| `@can` | Authorization check against policy |
| `@belongsTo` / `@belongsToMany` / `@hasMany` | Eloquent relationships |
| `@paginate` | Pagination support |
| `@search` | Full-text search |
| `@validator` | Input validation |
| `@spread` | Spread input fields to arguments |

### Configuration
GraphQL is configured via Lighthouse at `backend/config/lighthouse.php`.

## Development Setup

### Prerequisites
- Docker & Docker Compose
- Lando (recommended for local dev)
- Node.js 18+
- PHP 8.4+

### Local Development with Lando

```bash
# Start all services
lando start

# Install dependencies
lando composer install
lando yarn install

# Run migrations
lando artisan migrate

# Start frontend dev server
lando quasar dev
```

### Environment Configuration
Copy `.env.example` to `.env` and configure:
- Database credentials
- Mail settings
- OAuth provider credentials (Google, ORCID)
- Application URL

### Key Commands

| Command | Purpose |
|---------|---------|
| `lando artisan` | Laravel CLI |
| `lando composer` | PHP dependency management |
| `lando yarn` | Node dependency management |
| `lando quasar dev` | Start frontend dev server |
| `lando pandoc` | Document conversion |

## Testing

### Backend Tests
```bash
# Run all PHP tests
lando artisan test

# Or with PHPUnit directly
cd backend && ./vendor/bin/phpunit
```

Test files are in `backend/tests/`:
- `Feature/` - Integration tests
- `Unit/` - Unit tests
- `Api/` - API-specific tests

### Frontend Tests
```bash
cd client
yarn test        # Run Vitest
yarn test:e2e    # Run Cypress
```

### Linting
```bash
# PHP (PSR-12 / CakePHP standards)
cd backend && ./vendor/bin/phpcs

# JavaScript/Vue
cd client && yarn lint
```

## Deployment

### Docker Images
Built via multi-stage Dockerfiles:
- **Backend**: `backend/Dockerfile` (PHP-FPM with extensions)
- **Frontend**: `client/Dockerfile` (Node Alpine, Quasar SPA build)

### Kubernetes
Helm charts in `helm/` directory:
```bash
helm install pilcrow ./helm -f values.yaml
```

### CI/CD
GitHub Actions workflows in `.github/workflows/`:
- `testing.yml` - Runs tests on PRs
- `publish-docker.yaml` - Builds/pushes Docker images
- `publish-helm.yaml` - Publishes Helm charts
- `release-please.yml` - Automated releases

## Known Issues & Concerns

### GraphQL Security Configuration
**Location**: `backend/config/lighthouse.php:142-146`

The following security settings are currently **DISABLED**:
```php
'security' => [
    'max_query_complexity' => QueryComplexity::DISABLED,
    'max_query_depth' => QueryDepth::DISABLED,
    'disable_introspection' => DisableIntrospection::DISABLED,
],
```

**Risk**: Without query depth/complexity limits, malicious or poorly-constructed queries can cause memory exhaustion through:
1. Deeply nested relationship traversal
2. Circular references (User ↔ Submission ↔ Publication)
3. Unbounded list fetching

**Recommendation**: Enable limits in production:
```php
'max_query_complexity' => 500,
'max_query_depth' => 15,
```

### Pagination Configuration
**Location**: `backend/config/lighthouse.php:158-170`

Both `default_count` and `max_count` are set to `null` (unlimited).

**Recommendation**: Set a reasonable `max_count` (e.g., 100-500) to prevent excessive data fetching.

### Unpaginated Nested Relationships
Several schema fields return unbounded lists:
- `User.submissions: [Submission]!`
- `Submission.files: [SubmissionFile]!`
- `Publication.submissions: [Submission]!`
- `InlineComment.replies: [InlineCommentReply!]`

Consider adding pagination to these fields if they can grow large.

### Questionable Union Type
In `notification.graphql:36`:
```graphql
union Notifiable = Notification | User
```
A notification's `notifiable` being another `Notification` seems logically incorrect. Typically this should just be `User`.

## Useful File Locations

| Purpose | Location |
|---------|----------|
| Main GraphQL schema | `backend/graphql/schema.graphql` |
| Lighthouse config | `backend/config/lighthouse.php` |
| Laravel routes | `backend/routes/web.php`, `api.php` |
| Vue entry point | `client/src/App.vue` |
| Vue router | `client/src/router/routes.js` |
| GraphQL queries (client) | `client/src/graphql/` |
| E2E tests | `client/cypress/` |
| Helm values | `helm/values.yaml` |
| CI workflows | `.github/workflows/` |
| Lando config | `.lando.yml` |

## Key Architectural Patterns

1. **GraphQL-First API**: All data access goes through GraphQL, no REST endpoints for business logic
2. **Policy-Based Authorization**: Laravel policies with Lighthouse directives for fine-grained access control
3. **Eloquent Relationships**: Heavy use of Laravel's ORM for data modeling
4. **Composable Frontend**: Vue 3 Composition API with reusable composables in `client/src/use/`
5. **Apollo State Management**: GraphQL cache as primary frontend state
6. **Audit Logging**: All changes tracked via Laravel Auditing package
7. **Search Integration**: Laravel Scout with TNTSearch for full-text search capabilities
