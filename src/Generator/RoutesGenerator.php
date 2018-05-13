<?php

namespace Swagger\Generator;

use Swagger\V30\Object\Document;
use Swagger\V30\Object\PathItem;
use Swagger\Template;
use Swagger\V30\Object\MediaType;
use Swagger\V30\Object\Operation;
use Swagger\V30\Object\Reference;
use Swagger\V30\Object\RequestBody;
use Swagger\Ignore;

class RoutesGenerator extends AbstractGenerator
{
    /**
     * @var Template
     */
    protected $templateService;

    /**
     * @var HandlerGenerator
     */
    protected $handlerGenerator;

    /**
     * @var ModelGenerator
     */
    protected $modelGenerator;

    /**
     * @var Ignore
     */
    protected $ignoreService;

    /**
     * Constructor
     * ---
     * @param Template $templateService
     * @param HandlerGenerator $handlerGenerator
     * @param ModelGenerator $modelGenerator
     * @param Ignore $ignoreService
     */
    public function __construct(
        Template $templateService,
        HandlerGenerator $handlerGenerator,
        ModelGenerator $modelGenerator,
        Ignore $ignoreService
    ) {
        $this->templateService = $templateService;
        $this->handlerGenerator = $handlerGenerator;
        $this->modelGenerator = $modelGenerator;
        $this->ignoreService = $ignoreService;
    }

    /**
     * @param  Document $document
     * @param  string   $namespace
     * @param string $configPath
     */
    public function generateFromDocument(Document $document, string $namespace, string $configPath)
    {
        $routeConfigPath = rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'swagger.routes.php';

        if (!$this->ignoreService->isIgnored($routeConfigPath)) {
            $routes = [];
            foreach ($document->getPaths() as $path => $pathItem) {
                /** @var PathItem $pathItem **/
                $routes = $this->generateFromPathItem($path, $pathItem, $namespace, $routes);
            }

            $routes = $this->templateService->render('routes', [
                'routes' => $routes
            ]);

            $this->writeFile($routeConfigPath, $routes);

            return true;
        }

        return false;
    }

    /**
     * @param  PathItem $pathItem
     * @param  string   $namespace
     * @param  array    $routes
     *
     * @return array
     */
    public function generateFromPathItem(
        string $path,
        PathItem $pathItem,
        string $namespace,
        array $routes = []
    ): array {
        /** @var PathItem $pathItem **/

        foreach ($pathItem->getOperations() as $httpMethod => $operation) {
            $routes = $this->generateFromOperation($path, $operation, $httpMethod, $namespace, $routes);
        }

        return $routes;
    }

    public function generateFromOperation(
        string $path,
        Operation $operation,
        string $httpMethod,
        string $namespace,
        array $routes = []
    ): array {
        $requestBody = $operation->getRequestBody();

        $handlerNamespace = $this->handlerGenerator->getNamespace($namespace);
        $modelNamespace = $this->modelGenerator->getNamespace($namespace);

        $modelMiddleware = null;
        if ($requestBody instanceof RequestBody) {
            $mediaType = $requestBody->getContent()['application/json']??null;

            if ($mediaType instanceof MediaType) {
                $schema = $mediaType->getSchema();
                if ($schema instanceof Reference) {
                    $name = substr($schema->getRef(), strlen('#/components/schemas/'));

                    $modelMiddleware = $modelNamespace . '\\' . $name . '::class';
                }
            }
        }

        foreach ($operation->getParameters() as $parameter) {
            if ($parameter->getIn() == 'path') {
                $path = str_replace('{' . $parameter->getName() . '}', ':' . $parameter->getName(), $path);
            }
        }

        $handlerName = $this->handlerGenerator->getHandlerName($path);

        $routes[] = [
            'middleware' => $handlerNamespace . '\\' . $handlerName . '::class',
            'modelMiddleware' => $modelMiddleware,
            'path'       => $path,
            'method'     => $httpMethod,
            'name'       => $operation->getOperationId()
        ];

        return $routes;
    }
}