# Recruiter PHP Concurrency

A MongoDB-based locking system for PHP applications that provides distributed locking mechanisms to coordinate concurrent operations across multiple processes or servers.

## Features

- **Distributed Locking**: MongoDB-based locks that work across multiple processes and servers
- **Lock Management**: Acquire, release, refresh, and wait for locks with configurable timeouts
- **Retry Mechanisms**: Built-in retry logic for handling transient failures
- **Process Coordination**: Tools for managing process leadership and coordination
- **Timeout Handling**: Configurable timeouts with patience mechanisms

## Requirements

- PHP 8.4+
- MongoDB extension
- MongoDB server

## Installation

```bash
composer require recruiterphp/concurrency
```

## Basic Usage

### Creating and Using Locks

```php
use Recruiter\Concurrency\MongoLock;

// Create a lock instance
$lock = new MongoLock($collection, 'my-resource-id');

try {
    // Acquire lock for 5 minutes
    $lock->acquire(300);
    
    // Perform your critical operations here
    
} catch (LockNotAvailableException $e) {
    // Handle case when lock cannot be acquired
} finally {
    // Always release the lock
    $lock->release();
}
```

### Lock Waiting

```php
// Wait for lock to become available
$lock->wait(
    $polling = 10,           // Check every 10 seconds
    $maximumWaitingTime = 600 // Wait maximum 10 minutes
);
```

### Lock Refresh

```php
// Extend lock duration
$lock->refresh(600); // Extend for another 10 minutes
```

## Core Components

### Lock Interface

The main `Lock` interface provides:
- `acquire(int $duration)` - Acquire lock for specified duration
- `release(bool $force)` - Release the lock
- `refresh(int $duration)` - Extend lock duration  
- `wait(int $polling, int $maximumWaitingTime)` - Wait for lock availability
- `show()` - Get diagnostic information

### Implementations

- **MongoLock** - MongoDB-based distributed lock
- **NullLock** - No-op lock for testing/disabled scenarios

### Utilities

- **Leadership** - Process leadership coordination
- **Patience/TimeoutPatience** - Timeout handling mechanisms
- **InProcessRetry** - Retry logic for operations
- **PeriodicalCheck** - Periodic status checking

## Development

The project uses Docker for development. Available make targets:

### Setup
```bash
make build          # Build Docker image
make up             # Start services
make down           # Stop services
make install        # Install dependencies
make update         # Update dependencies
```

### Testing
```bash
make test           # Run tests (excluding long-running ones)
make test-long      # Run long-running tests only
```

The project includes property-based testing using Eris and traditional unit tests with PHPUnit.

### Code Quality
```bash
make phpstan        # Run static analysis
make rector         # Run automated refactoring
make fix-cs         # Fix code style issues
```

### Utilities
```bash
make shell          # Open shell in PHP container
make logs           # View container logs
make clean          # Clean up containers and volumes
```

## Code Quality

The project maintains high code quality with:

- **PHPStan** - Static analysis at high levels
- **PHP CS Fixer** - Code style enforcement  
- **Rector** - Automated refactoring and upgrades
- **Composer Normalize** - Normalized composer.json

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please ensure all tests pass and code quality checks are satisfied before submitting pull requests.