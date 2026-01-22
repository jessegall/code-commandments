<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use PhpParser\Node;

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
        return 'Controllers should not have too many private methods';
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
        // Only check controllers
        $ast = $this->parse($content);

        if (!$ast || !$this->isLaravelClass($ast, 'controller')) {
            return $this->righteous();
        }

        $maxPrivateMethods = (int) $this->config('max_private_methods', 3);
        $minMethodLines = (int) $this->config('min_method_lines', 3);

        $privateMethods = $this->findPrivateMethods($ast, $minMethodLines);

        if (count($privateMethods) <= $maxPrivateMethods) {
            return $this->righteous();
        }

        $methodNames = array_map(
            fn (Node\Stmt\ClassMethod $method) => $method->name->toString(),
            $privateMethods
        );

        $sins = [];
        $className = $this->getClassName($ast) ?? 'Controller';

        // Create one sin at the class level with all method names
        $firstMethod = $privateMethods[0];
        $sins[] = $this->sinAt(
            $firstMethod->getStartLine(),
            sprintf(
                '%s has %d private methods (%s) which exceeds the threshold of %d',
                $className,
                count($privateMethods),
                implode(', ', $methodNames),
                $maxPrivateMethods
            ),
            null,
            'Extract these methods to a service class or delegate/action class'
        );

        return $this->fallen($sins);
    }

    /**
     * Find all private methods that meet the minimum line threshold.
     *
     * @param array<Node> $ast
     * @return array<Node\Stmt\ClassMethod>
     */
    private function findPrivateMethods(array $ast, int $minLines): array
    {
        $methods = $this->findNodes($ast, Node\Stmt\ClassMethod::class);

        return array_values(array_filter($methods, function (Node\Stmt\ClassMethod $method) use ($minLines) {
            // Check if method is private
            if (!$method->isPrivate()) {
                return false;
            }

            // Calculate method line count
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            $lineCount = $endLine - $startLine + 1;

            return $lineCount >= $minLines;
        }));
    }
}
