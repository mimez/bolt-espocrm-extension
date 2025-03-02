<?php

namespace Bolt\Extension\MichaelMezger\Espocrm\Importer;

use Bolt\Storage\Entity\Taxonomy;
use Cocur\Slugify\Slugify;
use GuzzleHttp\Client;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Entity\Content;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Bolt\Filesystem\Manager as Filesystem;

class EspocrmImporter
{
    protected Client $guzzle;
    protected EntityManager $entityManager;
    protected Filesystem $filesystem;
    protected LoggerInterface $logger;

    protected static array $mapping = [
        'name' => ['strategy' => 'field', 'field' => 'title'],
        'slug' => ['strategy' => 'field', 'field' => 'slug'],
        'id' => ['strategy' => 'field', 'field' => 'external_id'],
        'body' => ['strategy' => 'field', 'field' => 'body'],
        'image1Id' => ['strategy' => 'image', 'field' => 'image'],
        'image2Id' => ['strategy' => 'image', 'field' => 'image2'],
        'image3Id' => ['strategy' => 'image', 'field' => 'image3'],
        'tags' => ['strategy' => 'tags'],
    ];

    public function __construct(Client $guzzle, EntityManager $entityManager, Filesystem $filesystem, LoggerInterface $logger = null)
    {
        $this->guzzle = $guzzle;
        $this->entityManager = $entityManager;
        $this->filesystem = $filesystem;
        $this->logger = $logger ?? new NullLogger();
    }

    public function import(): void
    {
        $this->logger->info('Starting import');
        $espoContents = $this->fetchContents();

        foreach ($espoContents as $espoContent) {
            $this->upsert($espoContent);
        }
    }

    protected function fetchContents(): array
    {
        $this->logger->info('Fetching contents from espo');
        $response = $this->guzzle->get('CContent', ['query' => ['whereGroup' => [['attribute' => 'syncBolt', 'type' => 'isTrue']]]]);
        $data = json_decode($response->getBody()->getContents(), true);

        return $data['list'] ?? [];
    }

    protected function upsert(array $espoContent): void
    {
        $this->logger->info('Processing espo-id {espoId}', ['espoId' => $espoContent['id']]);
        $content = $this->findContentByExternalIdOrCreateNew($espoContent['id']);
        $this->updateContent($content, $espoContent);
        $this->entityManager->getRepository('entries')->save($content);
        $this->updateEspo($espoContent);
        $this->logger->info('Finished processing espo-id {espoId}', ['espoId' => $espoContent['id']]);
    }

    protected function findContentByExternalIdOrCreateNew(string $externalId): Content
    {
        $repository = $this->entityManager->getRepository('entries');
        $content = $repository->findOneBy(['external_id' => $externalId]);

        if (!$content) {
            $this->logger->debug('Creating new content');
            $content = new Content();
            $content->setContenttype('entries');
            $content->setStatus('published');
        } else {
            $this->logger->debug('Loaded content {id}', ['id' => $content->getId()]);
        }

        return $content;
    }

    protected function updateContent(Content $content, array $espoContent): void
    {
        foreach (self::$mapping as $espoField => $boltMapping) {
            if (!isset($espoContent[$espoField])) {
                continue;
            }

            switch ($boltMapping['strategy']) {
                case 'field':
                    $content->set($boltMapping['field'], $espoContent[$espoField]);
                    break;
                case 'image':
                    $filePath = sprintf('%s/%s', $espoContent['slug'], $espoContent[str_replace('Id', 'Name', $espoField)]);
                    $fileContent = $this->guzzle->get(sprintf('Attachment/file/%s', $espoContent[$espoField]))->getBody()->getContents();
                    $this->saveFileOrReplace($filePath, $fileContent);
                    $content->set($boltMapping['field'], ['file' => $filePath]);
                    break;
                case 'tags':
                    $taxonomies = $this->entityManager->createCollection(Taxonomy::class);
                    foreach ($espoContent[$espoField] as $k => $tag) {
                        $taxonomy = new Taxonomy([
                            'name'         => $tag,
                            'content_id'   => $content->getId(),
                            'contenttype'  => (string) $content->getContenttype(),
                            'taxonomytype' => 'tags',
                            'slug'         => Slugify::create()->slugify($tag),
                            'sortorder'    => $k,
                        ]);
                        $taxonomies->add($taxonomy);
                    }

                    $content->setTaxonomy($taxonomies);
            }
        }
    }

    protected function saveFileOrReplace(string $path, string $fileContent): void
    {
        $files = $this->filesystem->getFilesystem('files');
        $file = $files->getFile($path);

        // falls datei existiert und unveränder, dann mache nix
        if ($file->exists() && $file->read() === $fileContent) {
            return;
        }
        if ($file->exists()) {
            $file->delete();
        }

        $files->write($path, $fileContent);
    }

    protected function updateEspo(array $espoContent): void
    {
        $this->logger->debug('Updating espo {espoId}', ['espoId' => $espoContent['id']]);
        $this->guzzle->put('CContent/' . $espoContent['id'], ['json' => ['syncBolt' => false]]);
    }
}
