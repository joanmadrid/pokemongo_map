<?php
namespace PokemonBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use PokemonBundle\Entity\Heatpoint;
use PokemonBundle\Entity\Locality;
use PokemonBundle\Entity\PokemonLocation;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetHeatmapCommand extends ContainerAwareCommand
{
    /** @var EntityManager $em */
    protected $em;

    protected function configure()
    {
        $this
            ->setName('pokemon:heatmap:reset')
            ->setDescription('Delete heatpoints and reset calculated locations');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        $plr = $this->em->getRepository('PokemonBundle:PokemonLocation');
        $q = $plr->createQueryBuilder('pl')->update()
            ->set('pl.inHeatmap', 0)
            ->getQuery();

        $q->execute();

        $output->writeln('Reset all locations');


        $query = $em->createQuery('DELETE PokemonBundle:Heatpoint h');
        $query->execute();

        $output->writeln('Delete all heatpoints');
    }


}