<?php
namespace PokemonBundle\Command;

use PokemonBundle\Entity\PokemonLocation;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ImportLocationsCommand extends ContainerAwareCommand
{
    private $csvParsingOptions = array(
        //'finder_in' => 'raw',
        //'finder_name' => 'locations.csv',
        'ignoreFirstLine' => false
    );

    protected function configure()
    {
        $this
            ->setName('pokemon:import:locations')
            ->setDescription('Import locations CSV')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'ex. raw'
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'ex. locations.csv'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine')->getManager();
        $repository = $this->getContainer()->get('doctrine')->getRepository('PokemonBundle:Pokemon');
        $csv = $this->parseCSV($input->getArgument('path'), $input->getArgument('file'));

        $i = 0;
        foreach($csv as $row) {
            $loc = new PokemonLocation();
            $pkmn = $repository->findOneByNumber(intval($row[0]));
            if ($pkmn) {
                $loc->setPokemon($pkmn);
                $loc->setLat($row[2]);
                $loc->setLon($row[3]);
                $loc->setDateCreated(\DateTime::createFromFormat('Y-m-d H:i:s', $row[4]));
                $doctrine->persist($loc);
                $i++;
            }
        }
        $output->writeln('Imported '.$i);
        $doctrine->flush();
    }

    private function parseCSV($path, $file)
    {
        $ignoreFirstLine = $this->csvParsingOptions['ignoreFirstLine'];

        $finder = new Finder();
        $finder->files()
            ->in($path)
            ->name($file)
        ;
        foreach ($finder as $file) { $csv = $file; }

        $rows = array();
        if (($handle = fopen($csv->getRealPath(), "r")) !== FALSE) {
            $i = 0;
            while (($data = fgetcsv($handle, null, ",")) !== FALSE) {
                $i++;
                if ($ignoreFirstLine && $i == 1) { continue; }
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $rows;
    }
}