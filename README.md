# Zenigata HTTP

> ⚠️ This project is in an early development stage. Feedback and contributions are welcome!

A lightweight, PSR-compliant HTTP framework for PHP 8.2+ built for flexibility and simplicity.

Built around standard interfaces and a composable architecture, it gives you full control over routing, middleware, request handling, and error responses — with sensible defaults that work out of the box.

## Requirements

- PHP >= 8.2
- A [PSR-7](https://www.php-fig.org/psr/psr-7/) and [PSR-17](https://www.php-fig.org/psr/psr-17/) implementation such as:
    - [`guzzlehttp/psr7`](https://github.com/guzzle/psr7)
    - [`laminas/laminas-diactoros`](https://github.com/laminas/laminas-diactoros)
    - [`nyholm/psr7`](https://github.com/Nyholm/psr7)
    - [`slim/psr7`](https://github.com/slimphp/Slim-Psr7)
    - [`sunrise/http-message`](https://github.com/sunrise-php/http-message)
- [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)

## Installation

```bash
composer require zenigata/http
```

## Overview

### Application

[`Application`](./src/Application.php) is the main entry point of the framework. It orchestrates the **full HTTP request lifecycle** and provides a **centralized API** to interact with all internal components: registering routes, middleware, and strategies, loading definitions from configuration files, propagating shared state such as a PSR-11 container or debug mode, and running or emitting responses.

### Routing

- [`Router`](./src/Routing/Router.php) matches incoming requests to registered routes using [FastRoute](https://github.com/nikic/FastRoute) under the hood. It supports individual routes, route groups with shared prefixes and middleware, and lazy resolution of string-based route definitions from a container or via reflection.
- [`Route`](./src/Routing/Route.php) provides a fluent API for defining routes for any HTTP method (`get`, `post`, `put`, `patch`, `delete`, `head`, `options`), as well as helpers for multiple methods (`map`) and catch-all definitions (`any`). Routes can be grouped with a shared prefix and middleware stack via `Route::group()`.

### Middleware

- [`MiddlewareDispatcher`](./src/Middleware/MiddlewareDispatcher.php) executes a stack of PSR-15 middleware sequentially and then delegates the request to the final handler. Middleware can be provided as instances or string identifiers resolved at dispatch time.
- [`BodyParserMiddleware`](./src/Middleware/BodyParserMiddleware.php) parses the incoming request body based on the `Content-Type` header and attaches the parsed data to the request. Ships with built-in parsers for **JSON**, **XML**, and **URL-encoded** bodies, all replaceable or extendable.

### Handling

[`RouteHandler`](./src/Handling/RouteHandler.php) invokes the matched handler and converts its return value into a PSR-7 response via a **response strategy** selected by the `Accept` header. Handlers are normalized into callables and invoked with route parameters as named arguments — both steps customizable via `HandlerNormalizerInterface` and `HandlerInvokerInterface`. Ships with strategies for **HTTP redirects**, **file downloads**, **JSON**, **XML**, and **plain text** (used by default).

### Error

[`ErrorHandler`](./src/Error/ErrorHandler.php) catches any `Throwable` thrown during the request lifecycle and converts it into a PSR-7 error response via an **error strategy** selected by the `Accept` header. Supports an optional **PSR-3 logger** and debug mode for full exception details. Ships with strategies for **HTML**, **JSON**, **XML**, and **plain text** (used by default).

[`HttpError`](./src/Error/HttpError.php) represents an HTTP-specific exception that maps directly to a status code (4xx–5xx):
- Validates that the code is within the **4xx–5xx** range.
- Automatically assigns the standard **reason phrase** if no message is provided.  
- Stores the original `ServerRequestInterface` that caused the error, accessible via `getRequest()`. 

### Runtime

- [`RequestInitializer`](./src/Runtime/RequestInitializer.php) builds a PSR-7 `ServerRequestInterface` from PHP superglobals, normalizing headers, cookies, uploaded files, and protocol version.
- [`ResponseEmitter`](./src/Runtime/ResponseEmitter.php) sends the final PSR-7 response to the client by emitting the body in streaming chunks — minimizing memory usage for large payloads.
- [`HttpRunner`](./src/Runtime/HttpRunner.php) ties initialization and emission together: it creates the server request if none is provided, passes it to the application, and emits the resulting response.

## Usage

### Minimal Setup

```php
use Zenigata\Http\Application;
use Zenigata\Http\Routing\Route;

$app = new Application();

$app->addRoute(Route::get('/hello', fn() => 'Hello, world!'));

$app->run();
```

The handler can return any value. `RouteHandler` picks the right response strategy based on the `Accept` header, falling back to plain text if none matches.

### Routes

```php
use Zenigata\Http\Routing\Route;

$app->addRoute(Route::get('/users', [UserController::class, 'index']));
$app->addRoute(Route::post('/users', [UserController::class, 'store']));
$app->addRoute(Route::delete('/users/{id}', [UserController::class, 'destroy']));

// Multiple methods on the same path
$app->addRoute(Route::map(['GET', 'POST'], '/contact', ContactController::class));

// All HTTP methods
$app->addRoute(Route::any('/catch-all', FallbackHandler::class));

// Route groups with shared prefix and middleware
$app->addRoute(Route::group('/api', fn() => [
        Route::get('/users', [UserController::class, 'index']),
        Route::post('/users', [UserController::class, 'store']),
    ],
    middleware: [AuthMiddleware::class])
);
```

### Handlers

Handlers can be defined in several ways:

```php
// Closure
Route::get('/hello', fn() => 'Hello, world!');

// Invokable class
Route::get('/hello', InvokableHandler::class);

// [Class, method] pair
Route::get('/users', [UserController::class, 'index']);

// PSR-15 RequestHandlerInterface
Route::get('/users', Psr15Handler::class);
```

When defined as strings, handlers are resolved from the container if available, or instantiated via reflection otherwise (the class must have no required constructor parameters).

Route parameters are spread to the handler as **named arguments**, so parameter names must match the route placeholders:

```php
Route::get('/users/{id}', function (ServerRequestInterface $request, string $id) {
    return ['id' => $id];
});
```

### Middleware

```php
use Zenigata\Http\Middleware\BodyParserMiddleware;

// Global middleware — applied to every request, in FIFO order
$app->addMiddleware(new BodyParserMiddleware());
$app->addMiddleware(AuthMiddleware::class); // resolved from container or reflection

// Route-level middleware
$app->addRoute(Route::get('/admin', AdminController::class, middleware: [
    AuthMiddleware::class,
    RateLimitMiddleware::class,
]));
```

### Redirects

Return an `HttpRedirect` from any handler:

```php
use Zenigata\Http\Handling\Strategy\HttpRedirect;

Route::get('/old-path', fn() => new HttpRedirect('/new-path', 301));
```

### File Downloads

Return a `SplFileInfo` from any handler:

```php
Route::get('/download', fn() => new SplFileInfo('/path/to/file.pdf'));
```

### Error Handling

Any uncaught exception is passed to `ErrorHandler`, which selects the right strategy based on the `Accept` header. In debug mode, responses include the full exception details:

```php
$app = new Application(debug: true);
```

Attach a PSR-3 logger to record errors alongside request context:

```php
$app = new Application(errorHandler: new ErrorHandler(logger: $logger));
```

Throw an `HttpError` to produce a specific HTTP error response:

```php
use Zenigata\Http\Error\HttpError;

throw new HttpError($request, 404);
throw new HttpError($request, 403, 'Access denied.');
```

### Container Integration

Pass any PSR-11 container to resolve middleware, handlers, and strategies by service ID:

```php
$app = new Application(container: $container);

$app->addMiddleware('app.middleware.auth');
$app->addRoute(Route::get('/users', 'app.handler.users'));
```

The container is automatically propagated to all internal components that support it.

### File-based Configuration

Split routes, middleware, and strategies across separate configuration files. Each file must return an **array** of definitions:

```php
// config/routes.php
use Zenigata\Http\Routing\Route;

return [
    Route::get('/users', [UserController::class, 'index']),
    Route::post('/users', [UserController::class, 'store']),
    Route::group(
        prefix: '/admin',
        routes: fn() => [Route::get('/dashboard', [AdminController::class, 'dashboard'])],
        middleware: [AuthMiddleware::class]
    ),
];
```

```php
// config/middleware.php
return [
    \App\Middleware\CorsMiddleware::class,
    \App\Middleware\BodyParserMiddleware::class,
];
```

Load them at bootstrap:

```php
$app = new Application();

$app->loadRoutes(__DIR__ . '/config/routes.php')
    ->loadMiddleware(__DIR__ . '/config/middleware.php')
    ->loadErrorStrategies(__DIR__ . '/config/error_strategies.php')
    ->loadResponseStrategies(__DIR__ . '/config/response_strategies.php')
    ->run();
```

## Extensibility

Zenigata HTTP is designed for flexibility and extensibility. Every internal component can be replaced by passing a custom implementation to the constructor:

```php
$app = new Application(
    dispatcher:   new MyMiddlewareDispatcher(),
    router:       new MyRouter(),
    routeHandler: new MyRouteHandler(),
    errorHandler: new MyErrorHandler(),
);
```

Custom response and error strategies can be registered at any time:

```php
$app->addResponseStrategy(new CsvResponseStrategy());
$app->addErrorStrategy(new SentryErrorStrategy());
```

as well as set a default response strategy:
```php
$app->setDefaultResponseStrategy('json');
$app->setDefaultErrorStrategy('json');
```

You can also extend `Application` directly and override any `protected` method to customize specific behaviors without replacing entire components.

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

Keep the implementation minimal, focused, and well-documented, making sure to update tests accordingly.

See [CONTRIBUTING](./CONTRIBUTING.md) for more information.

## License

This library is licensed under the MIT license. See [LICENSE](./LICENSE) for more information.
