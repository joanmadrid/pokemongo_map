<?php
namespace PokemonBundle\Command;

use PokemonBundle\Entity\Pokemon;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ImportPokemonsCommand extends ContainerAwareCommand
{
    private $csvParsingOptions = array(
        'finder_in' => 'raw',
        'finder_name' => 'pokemons.csv',
        'ignoreFirstLine' => false
    );

    protected function configure()
    {
        $this
            ->setName('pokemon:import:pokemons')
            ->setDescription('Import pokemons CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine')->getManager();
        $csv = $this->parseCSV();
        //var_dump($csv);

        $i = 0;
        foreach($csv as $row) {
            $pkmn = new Pokemon();
            $pkmn->setNumber($row[0]);
            $pkmn->setName($row[1]);
            //type1 & type2
            $doctrine->persist($pkmn);
            $i++;
        }
        $output->writeln('Imported '.$i);
        $doctrine->flush();


    }

    private function parseCSV()
    {
        $ignoreFirstLine = $this->csvParsingOptions['ignoreFirstLine'];

        $finder = new Finder();
        $finder->files()
            ->in($this->csvParsingOptions['finder_in'])
            ->name($this->csvParsingOptions['finder_name'])
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