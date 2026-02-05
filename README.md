# LMU IoT Portal

> Multi-tenant IoT device management platform for monitoring and controlling connected devices

## 🚀 Quick Start

### Prerequisites
- PHP 8.4+
- Composer
- Node.js & NPM
- PostgreSQL
- Laravel Herd (recommended) or your preferred local development environment

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/tharindarodrigo/lmu-iot-portal.git
   cd lmu-iot-portal
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   # Create your PostgreSQL database
   php artisan migrate --seed
   ```

5. **Build assets**
   ```bash
   npm run build
   # OR for development with hot reload:
   npm run dev
   ```

6. **Configure git workflow** (required for contributors)
   ```bash
   ./scripts/setup-dev.sh
   ```

### Access the Application

If using Laravel Herd:
```
https://lmu-iot-portal.test
```

Otherwise, start the development server:
```bash
php artisan serve
```

## 🧪 Development

### Running Tests
```bash
# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/DeviceTypeTest.php

# Run with coverage
php artisan test --coverage
```

### Code Quality

```bash
# Format code (Laravel Pint)
vendor/bin/pint --dirty --format agent

# Static analysis (PHPStan)
vendor/bin/phpstan analyse

# Run all quality checks
composer run x      # with auto-fix
composer run x-test # dry-run (CI mode)
```

## 🔄 Contributing

This project follows a structured git-flow workflow with automated enforcement.

### Quick Workflow

1. **Start a new feature**
   ```bash
   ./scripts/new-feature.sh
   # Follow the prompts to create your feature branch
   ```

2. **Make your changes**
   ```bash
   # Commits are automatically validated
   git commit -m "US-1: Add ProtocolType enum"
   ```

3. **Push and create PR**
   ```bash
   git push origin feature/us-1-device-types
   # Then open a PR on GitHub
   ```

4. **Automated checks**
   - ✅ Commit message format (`US-<number>:` prefix)
   - ✅ Branch naming (`feature/us-<number>-<slug>`)
   - ✅ Issue linking in PR
   - ✅ Tests, Pint, PHPStan

📚 **Full workflow details**: See [CONTRIBUTING.md](CONTRIBUTING.md)

## 📂 Project Structure

```
app/
├── Domain/           # Domain logic (IoT, Authorization, etc.)
├── Filament/         # Admin panel resources
│   ├── Admin/        # Admin-only resources
│   └── Portal/       # Organization portal resources
├── Http/             # Controllers, middleware
└── Policies/         # Authorization policies

database/
├── migrations/       # Database schema
├── factories/        # Model factories
└── seeders/          # Database seeders

tests/
├── Feature/          # Feature tests
├── Filament/         # Filament-specific tests
└── Unit/             # Unit tests

plan/                 # Project planning docs
├── 01-erd-core.md    # Core ERD
├── 02-erd-extension.md  # Extended ERD
└── 03-backlog.md     # User stories backlog
```

## 📊 Features

### Phase 1 - Core Schema & Admin UI
- ✅ Multi-tenant organization management
- ✅ Role-based access control (enum-permission)
- 🔄 Device type catalog with protocol configs
- 🔄 Schema versioning with parameter definitions
- 🔄 Device registration and provisioning
- 🔄 Latest readings snapshot

### Phase 2 - Advanced Admin Features
- 📋 Rich schema version editor
- 📋 Guided device provisioning wizard
- 📋 Bulk operations

### Phase 3 - Telemetry Ingestion
- 📋 MQTT/HTTP ingestion pipeline
- 📋 Parameter validation & transformation
- 📋 Derived parameter calculation
- 📋 Historical telemetry logs

### Phase 4 - Dashboards & Visualization
- 📋 Real-time device monitoring
- 📋 Time-series charts
- 📋 Alerts and notifications

### Phase 5 - Device Control
- 📋 Command sending (MQTT downlink)
- 📋 Desired state management
- 📋 Command execution tracking

### Phase 6 - Simulation & Evaluation
- 📋 IoT device simulator
- 📋 Performance testing
- 📋 Demo data generation

## 🛠 Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **UI**: Filament 5, Livewire 4, Alpine.js, Tailwind CSS
- **Database**: PostgreSQL (with JSONB for flexible schemas)
- **Queue**: Laravel Horizon (Redis)
- **Testing**: Pest 4
- **Code Quality**: Pint, PHPStan (Level 8), Rector
- **Protocols**: MQTT (php-mqtt/client), HTTP

## 📖 Documentation

- [Contributing Guide](CONTRIBUTING.md) - Git workflow, code standards
- [Agent Guidelines](AGENTS.md) - AI coding assistant guidelines
- [Project Planning](plan/) - ERD, backlog, technical decisions

## 🔐 Security

If you discover a security vulnerability, please email security@example.com. All security vulnerabilities will be promptly addressed.

## 📄 License

This project is licensed under the MIT License.

## 🙏 Acknowledgments

- Laravel & Filament communities
- LMU faculty and contributors

---

**Current Status**: Phase 1 Development (see [Backlog](plan/03-backlog.md))
