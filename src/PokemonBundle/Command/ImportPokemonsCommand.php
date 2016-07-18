<?php
namespace PokemonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPokemonsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('pokemon:import:pokemons')
            ->setDescription('Import pokemons CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('test');
    }
}