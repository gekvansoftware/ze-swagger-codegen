<?php

namespace Swagger\Generator;

use Swagger\V30\Object\Document;
use Swagger\V30\Object\PathItem;
use Swagger\Template;
use Swagger\Ignore;

class HandlerGenerator extends AbstractGenerator
{
    /**
     * @var Template
     */
    protected $templateService;

    /**
     * @var Ignore
     */
    protected $ignoreService;

    /**
     * Constructor
     * ---
     * @param Template $templateService
     * @param Ignore $ignoreService
     */
    public function __construct(Template $templateService, Ignore $ignoreService)
    {
        $this->templateService = $templateService;
        $this->ignoreService = $ignoreService;
    }

    /**
     * @param  Document $document
     * @param  string   $namespacePath
     * @param  string $namespace
     */
    public function generateFromDocument(Document $document, string $namespacePath, string $namespace)
    {
        foreach ($document->getPaths() as $path => $pathItem) {
            /** @var PathItem $pathItem **/

            $this->generateFromPathItem($pathItem, $path, $namespacePath, $namespace);
        }
    }

    /**
     * @param  PathItem $pathItem
     * @param  string   $path
     * @param  string   $namespacePath
     * @param  string   $namespace
     *
     * @return string|null
     */
    public function generateFromPathItem(
        PathItem $pathItem,
        string $path,
        string $namespacePath,
        string $namespace
    ): ?string {
        /** @var PathItem $pathItem **/

        $handlerName = $this->getHandlerName($path);

        $handlerPath = $namespacePath . DIRECTORY_SEPARATOR . 'Handler' . DIRECTORY_SEPARATOR;

        $savePath = $handlerPath . $handlerName . '.php';

        if (!$this->ignoreService->isIgnored($savePath)) {
            $handler = $this->templateService->render('handler', [
                'className'  => $handlerName,
                'namespace'  => $this->getNamespace($namespace),
                'operationMethods' => array_keys($pathItem->getOperations())
            ]);

            $this->writeFile($savePath, $handler);

            return $handlerName;
        }

        return null;
    }

    /**
     * @param  string $path
     *
     * @return string
     */
    public function getHandlerName(string $path): string
    {
        return $this->toModelName($path) . 'Handler';
    }

    /**
     * @param  string $namespace
     *
     * @return string
     */
    public function getNamespace(string $namespace): string
    {
        return $namespace . '\Handler';
    }
}