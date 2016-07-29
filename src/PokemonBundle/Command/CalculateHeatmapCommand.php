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

class CalculateHeatmapCommand extends ContainerAwareCommand
{
    /** @var EntityManager $em */
    protected $em;

    const DIVISIONS_LAT = 90;
    const DIVISIONS_LON = 180;

    protected function configure()
    {
        $this
            ->setName('pokemon:heatmap:calculate')
            ->setDescription('Calculate heatmap')
            ->addArgument(
                'latDiv',
                InputArgument::OPTIONAL,
                'lat sector divisions ('.self::DIVISIONS_LAT.' default)',
                self::DIVISIONS_LAT
            )
            ->addArgument(
                'lonDiv',
                InputArgument::OPTIONAL,
                'lon sector divisions ('.self::DIVISIONS_LON.' default)',
                self::DIVISIONS_LON
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();
        $this->em = $em;
//        /** @var EntityRepository $repository */
//        $repository = $this->getContainer()->get('doctrine')->getRepository('PokemonBundle:PokemonLocation');
//        $lr = $this->getContainer()->get('doctrine')->getRepository('PokemonBundle:Locality');
//        /** @var Query $query */
//        $query = $repository->createQueryBuilder('pl')
//            ->where('pl.calculated = :calculated')
//            ->setParameter('calculated', false)
//            ->getQuery();
//
//        $pokemonLocations = $query->getResult();


        $output->writeln('Generating sectors...');
        $sectors = $this->getSectors($input->getArgument('latDiv'), $input->getArgument('lonDiv'));
        $output->writeln('...'.count($sectors).' sectors generated');

        $output->writeln('Generating heatpoints...');
        $heatPoints = array();
        $i = 0;
        foreach ($sectors as $key=>$sector) {
            $points = $this->findPointsInbound($sector[1], $sector[3]);
            $heatPoint = $this->calculateHeatPoint($points, $sector);
            if ($heatPoint) {
                $output->writeln('...on sector #'.$key);
                $output->writeln('...'.count($points).' points found inbound');
                $heatPoints[] = $heatPoint;
//                var_dump($heatPoint);
                $hp = $this->addHeatpoint($heatPoint);
                $this->em->persist($hp);
                $output->writeln('...['.$heatPoint['lat'].','.$heatPoint['lon'].','.$heatPoint['force'].'] heatpoint generated');
            } else {
//                $output->writeln('...no points found');
            }

            $i++;

            if ($i % 1000 == 0 ) {
                $output->writeln('['.$i.' sectors checkpoint]');
            }

            $this->em->flush();
        }
        $output->writeln('...'.count($heatPoints).' heatpoints generated total');


    }

    private function getSectors($latDiv, $lonDiv)
    {
        $latBounds = array(-90, 90);
        $latRange = abs($latBounds[0]) + $latBounds[1];
//        echo '<br />absolute lat: '.$latRange;
        $lonBounds = array(-180, 180);
        $lonRange = abs($lonBounds[0]) + abs($lonBounds[1]);
//        echo '<br />absolute lon: '.$lonRange;

        $sectors = array();
        $latCurr = $latBounds[0];
        $lonCurr = $lonBounds[0];
//        echo '<br />current lat: '.$latCurr;
//        echo '<br />current lon: '.$lonCurr;

        $latInc = $latRange / $latDiv;
        $lonInc = $lonRange / $lonDiv;
//        echo '<br />incr lat: '.$latInc;
//        echo '<br />incr lon: '.$lonInc;
//
//        echo '<br />===== start algorithm =====';

        for ($i = 1; $i <= $latDiv; $i++) {
            $lonCurr = $lonBounds[0];
            for ($j = 1; $j <= $lonDiv; $j++) {
                $sectors[] = array(
                    array($latCurr + $latInc, $lonCurr),
                    array($latCurr, $lonCurr),
                    array($latCurr, $lonCurr + $lonInc),
                    array($latCurr + $latInc, $lonCurr + $lonInc)
                );
                $lonCurr += $lonInc;
            }
            $latCurr += $latInc;
        }
//        echo '<br />===== end algorithm =====';
//        echo '<br />current lat: '.$latCurr;
//        echo '<br />current lon: '.$lonCurr;

        return $sectors;
    }

    private function findPointsInbound($latlon1, $latlon2)
    {
        /** @var EntityRepository $repository */
        $repository = $this->em->getRepository('PokemonBundle:PokemonLocation');

        /** @var Query $query */
        $query = $repository->createQueryBuilder('pl')
            ->where('pl.lat > :latMin')
            ->andWhere('pl.lat <= :latMax')
            ->andWhere('pl.lon > :lonMin')
            ->andWhere('pl.lon <= :lonMax')
            ->andWhere('pl.inHeatmap = :inHeatmap')
            ->setParameters(array(
                'latMin' => $latlon1[0],
                'latMax' => $latlon2[0],
                'lonMin' => $latlon1[1],
                'lonMax' => $latlon2[1],
                'inHeatmap' => false
            ))
            ->getQuery();

        $results = $query->getResult();

        return $results;
    }

    private function calculateHeatPoint($points, $sector)
    {
        $heatPoint = array();

        /** @var PokemonLocation $point */
        foreach ($points as $point) {
            $point->setInHeatmap(true);
            $this->em->persist($point);
        }

        //calculate force
        $heatPoint['force'] = count($points);

        if ($heatPoint['force'] == 0) {
            $heatPoint = null;
        } else {
            //calculate middle
            $heatPoint['lat'] = $sector[2][0] + (($sector[3][0] - $sector[1][0]) / 2);
            $heatPoint['lon'] = $sector[2][1] - (($sector[3][1] - $sector[1][1]) / 2);
        }

        return $heatPoint;
    }

    private function addHeatpoint($heatPoint)
    {
        /** @var EntityRepository $repository */
        $repository = $this->em->getRepository('PokemonBundle:Heatpoint');
        /** @var Query $query */
        $query = $repository->createQueryBuilder('h')
            ->where('h.lat = :lat')
            ->andWhere('h.lon = :lon')
            ->setParameters(array('lat' => $heatPoint['lat'], 'lon' => $heatPoint['lon']))
            ->getQuery();

        $hp = $query->getOneOrNullResult();

        if ($hp) {
            $hp->add($heatPoint['force']);

        } else {
            $hp = new Heatpoint();
            $hp->setLat($heatPoint['lat']);
            $hp->setLon($heatPoint['lon']);
            $hp->setCount($heatPoint['force']);
        }
        return $hp;
    }


}