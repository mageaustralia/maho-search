<?php

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lucene:reindex',
    description: 'Rebuild the Lucene search index for all or specific stores',
)]
class LuceneReindex extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store code to reindex (default: all stores)');
        $this->addOption('entity', 'e', InputOption::VALUE_OPTIONAL, 'Entity types to reindex: products,categories,cms (default: all)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        if (!Mage::helper('lucenesearch')->isEnabled()) {
            $output->writeln('<error>Lucene Search is disabled in configuration</error>');
            return Command::FAILURE;
        }

        $storeCode = $input->getOption('store');
        $startTime = microtime(true);

        /** @var \MageAustralia_LuceneSearch_Model_Indexer $indexer */
        $indexer = Mage::getModel('lucenesearch/indexer');

        if ($storeCode) {
            try {
                $store = Mage::app()->getStore($storeCode);
            } catch (\Throwable $e) {
                $output->writeln("<error>Store '{$storeCode}' not found</error>");
                return Command::FAILURE;
            }

            $output->write("Reindexing store <info>{$storeCode}</info>... ");
            $stats = $indexer->reindexStore((int) $store->getId());
            $this->_printStats($output, $stats);
        } else {
            $results = $indexer->reindexAll();
            foreach ($results as $code => $stats) {
                $output->write("Store <info>{$code}</info>: ");
                $this->_printStats($output, $stats);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $output->writeln(sprintf("\n<info>Done!</info> (%.2fs)", $duration));

        return Command::SUCCESS;
    }

    private function _printStats(OutputInterface $output, array $stats): void
    {
        $parts = [];
        if ($stats['products'] > 0) {
            $parts[] = "{$stats['products']} products";
        }
        if ($stats['categories'] > 0) {
            $parts[] = "{$stats['categories']} categories";
        }
        if ($stats['cms_pages'] > 0) {
            $parts[] = "{$stats['cms_pages']} CMS pages";
        }
        $output->writeln(implode(', ', $parts) ?: 'no documents indexed');
    }
}
