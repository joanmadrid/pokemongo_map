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

class ForcesHeatmapCommand extends ContainerAwareCommand
{
    /** @var EntityManager $em */
    protected $em;

    protected function configure()
    {
        $this
            ->setName('pokemon:heatmap:forces')
            ->setDescription('Calculate proportional forces min / max from 0 to 1');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        $hr = $this->em->getRepository('PokemonBundle:Heatpoint');

        $query = $hr->createQueryBuilder('h');
        $query->select('h.count')->orderBy('h.count', 'DESC')->setMaxResults(1);
        $max = $query->getQuery()->getSingleScalarResult();
        $output->writeln('Max: '.$max);

//        $query = $hr->createQueryBuilder('h');
//        $query->select('h.count')->orderBy('h.count', 'ASC')->setMaxResults(1);
//        $min = $query->getQuery()->getSingleScalarResult();
//        $output->writeln('Min: '.$min);

        //ir heatpoint a heatpoint asignando una fuerza proporcional al maximo.
    }


}