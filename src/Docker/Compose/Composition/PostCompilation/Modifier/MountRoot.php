<?php

declare(strict_types=1);

namespace DefaultValue\Dockerizer\Docker\Compose\Composition\PostCompilation\Modifier;

use DefaultValue\Dockerizer\Docker\Compose\Composition\PostCompilation\ModificationContext;

/**
 * Change mounted volume paths from `.` (dot) to the correct path relative to the current folder
 */
class MountRoot implements \DefaultValue\Dockerizer\Docker\Compose\Composition\PostCompilation\ModifierInterface
{
    /**
     * @param \DefaultValue\Dockerizer\Filesystem\Filesystem $filesystem
     */
    public function __construct(private \DefaultValue\Dockerizer\Filesystem\Filesystem $filesystem)
    {
    }

    /**
     * @inheritDoc
     */
    public function modify(ModificationContext $modificationContext): void
    {
        $yamlContent = $modificationContext->getCompositionYaml();
        $dockerComposeDir = $modificationContext->getDockerComposeDir();
        $projectRoot = $modificationContext->getProjectRoot();

        foreach ($yamlContent['services'] as &$service) {
            if (!isset($service['volumes'])) {
                continue;
            }

            foreach ($service['volumes'] as $index => $volume) {
                $volumeConfiguration = explode(':', $volume);

                // If the file is present in the docker-compose.yml directory - do not mount it
                if (
                    $volumeConfiguration[0] !== '.'
                    && $this->filesystem->exists($dockerComposeDir . $volumeConfiguration[0])
                ) {
                    continue;
                }


                if (
                    $volumeConfiguration[0] === '.'
                    // If the file is not present in the composition dir and is present on the wed root - mount it
                    || $this->filesystem->exists($projectRoot . $volumeConfiguration[0])
                ) {
                    $volumeConfiguration[0] = $this->filesystem->makePathRelative(
                        $volumeConfiguration[0] === '.' ? $projectRoot : $projectRoot . $volumeConfiguration[0],
                        $dockerComposeDir
                    );
                    $service['volumes'][$index] = implode(':', $volumeConfiguration);
                }
            }
        }

        unset($service);
        $modificationContext->setCompositionYaml($yamlContent);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 100;
    }
}
