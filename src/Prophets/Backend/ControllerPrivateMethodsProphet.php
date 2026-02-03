<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractMethods;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterPrivateMethod;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Controllers should not have too many private methods.
 *
 * When a controller accumulates multiple private helper methods, it's a sign
 * that business logic should be extracted to a service or delegate class.
 */
class ControllerPrivateMethodsProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Extract private methods to service classes when controller exceeds limit';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Controllers should remain thin and focused on handling HTTP requests.
When a controller accumulates multiple private helper methods, it indicates
that business logic is leaking into the controller layer.

Extract this logic to:
- A dedicated service class (e.g., UserService, OrderProcessor)
- A delegate/action class (e.g., CreateUserAction)
- A domain object or value object

Bad:
    class UserController extends Controller
    {
        public function store(Request $request)
        {
            $data = $this->validateData($request);
            $user = $this->createUser($data);
            $this->sendNotifications($user);
            $this->logActivity($user);
            return response()->json($user);
        }

        private function validateData($request) { /* ... */ }
        private function createUser($data) { /* ... */ }
        private function sendNotifications($user) { /* ... */ }
        private function logActivity($user) { /* ... */ }
    }

Good:
    class UserController extends Controller
    {
        public function store(StoreUserRequest $request, UserService $service)
        {
            $user = $service->create($request->validated());
            return response()->json($user);
        }
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $maxPrivateMethods = (int) $this->config('max_private_methods', 3);
        $minMethodLines = (int) $this->config('min_method_lines', 3);

        return PhpPipeline::make($filePath, $content)
            ->onlyControllers()
            ->pipe(new ExtractMethods)
            ->pipe((new FilterPrivateMethod)->withMinLines($minMethodLines))
            ->returnRighteousWhen(fn (PhpContext $ctx) => count($ctx->methods) <= $maxPrivateMethods)
            ->mapToSins(fn (PhpContext $ctx) => $this->createSin($ctx, $maxPrivateMethods))
            ->judge();
    }

    /**
     * Create a sin for having too many private methods.
     */
    private function createSin(PhpContext $ctx, int $maxPrivateMethods): ?\JesseGall\CodeCommandments\Results\Sin
    {
        if (empty($ctx->methods)) {
            return null;
        }

        $methodNames = array_map(
            fn ($m) => $m['method']->name->toString(),
            $ctx->methods
        );

        $className = $ctx->getClassName() ?? 'Controller';
        $firstMethod = $ctx->methods[0]['method'];

        return $this->sinAt(
            $firstMethod->getStartLine(),
            sprintf(
                '%s has %d private methods (%s) which exceeds the threshold of %d',
                $className,
                count($ctx->methods),
                implode(', ', $methodNames),
                $maxPrivateMethods
            ),
            null,
            'Extract these methods to a service class or delegate/action class'
        );
    }
}
