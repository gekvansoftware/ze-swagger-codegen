<?php

namespace Swagger\Generator;

use Swagger\V30\Schema\Document;
use Swagger\V30\Schema\Schema;
use Swagger\V30\Schema\Reference;
use Swagger\Template;
use Swagger\Ignore;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HydratorGenerator extends AbstractGenerator
{
    /**
     * @var Template
     */
    protected $templateService;

    /**
     * @var ModelGenerator
     */
    protected $modelGenerator;

    /**
     * @var Ignore
     */
    protected $ignoreService;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Constructor
     * ---
     * @param Template $templateService
     * @param ModelGenerator $modelGenerator
     * @param Ignore $ignoreService
     */
    public function __construct(
        Template $templateService,
        ModelGenerator $modelGenerator,
        Ignore $ignoreService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->templateService = $templateService;
        $this->modelGenerator = $modelGenerator;
        $this->ignoreService = $ignoreService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param  Document $document
     * @param  string   $namespacePath
     * @param  string $namespace
     */
    public function generateFromDocument(Document $document, string $namespacePath, string $namespace)
    {
        if ($document->getComponents()) {
            foreach ($document->getComponents()->getSchemas() as $name => $schema) {
                /** @var Schema $schema **/

                if ($schema instanceof Schema) {
                    $this->generateFromSchema($schema, $name, $namespacePath, $namespace);
                }
            }
        }
    }

    /**
     * @param  Schema   $schema
     * @param  string   $name
     * @param  string   $namespacePath
     * @param  string   $namespace
     *
     * @return string|null
     */
    public function generateFromSchema(
        Schema $schema,
        string $name,
        string $namespacePath,
        string $namespace
    ): ?string {
        /** @var Schema|Reference $schema **/

        $hydratorPath = $namespacePath . DIRECTORY_SEPARATOR . 'Hydrator' . DIRECTORY_SEPARATOR;
        $hydratorNamespace = $this->getNamespace($namespace);
        $modelNamespace = $this->modelGenerator->getNamespace($namespace);

        $modelName = $this->toModelName($name);
        $path = $hydratorPath . $modelName . 'Hydrator.php';

        if (!$this->ignoreService->isIgnored($path)) {
            $hydrator = $this->templateService->render('model-hydrator', [
                'className'  => $modelName . 'Hydrator',
                'namespace'  => $hydratorNamespace,
                'modelName' => $modelName,
                'modelNamespace' => $modelNamespace,
                'properties' => $this->modelGenerator->getModelProperties($schema, $name)
            ]);

            $this->writeFile($path, $hydrator);

            $this->eventDispatcher->dispatch('swagger.codegen.generator.generated', new GenericEvent([
                'generator' => 'Hydrator',
                'name' => $modelName . 'Hydrator'
            ]));

            return $modelName . 'Hydrator';
        }

        return null;
    }

    /**
     * @param  string $namespace
     *
     * @return string
     */
    public function getNamespace(string $namespace): string
    {
        return $namespace . '\Hydrator';
    }

    /**
     * @param  Document $document
     * @param  string   $namespace
     *
     * @return string[]
     */
    public function getHydratorClasses(Document $document, string $namespace)
    {
        $hydratorNamespace = $this->getNamespace($namespace);
        $modelNamespace = $this->modelGenerator->getNamespace($namespace);

        $hydrators = [];

        if ($document->getComponents()) {
            foreach (array_keys($document->getComponents()->getSchemas()) as $name) {
                /** @var Schema|Reference $schema **/

                $modelName = $this->toModelName($name);

                $hydrators[] = [
                    'hydrator' => $hydratorNamespace . '\\' . $modelName . 'Hydrator::class',
                    'model'    => $modelNamespace . '\\' . $modelName . '::class'
                ];
            }
        }

        return $hydrators;
    }
}
