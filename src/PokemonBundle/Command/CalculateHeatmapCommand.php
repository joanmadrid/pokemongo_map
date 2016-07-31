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
use Symfony\Component\Stopwatch\Stopwatch;

ini_set('memory_limit', -1);

class CalculateHeatmapCommand extends ContainerAwareCommand
{
/*
    Cosas para mejorar el rendimiento y evitar memory leak

    unset($obj);

    $this->em->detach($obj);
    $this->em->flush();
    $this->em->clear();

    $this->em->getConnection()->getConfiguration()->setSQLLogger(null);


    doctrine:
        dbal:
            connections:
                conn1:
                    driver: ...
                    ...
                    logging: false
                    profiling: false
 */


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
                'lat sector divisions (' . self::DIVISIONS_LAT . ' default)',
                self::DIVISIONS_LAT
            )
            ->addArgument(
                'lonDiv',
                InputArgument::OPTIONAL,
                'lon sector divisions (' . self::DIVISIONS_LON . ' default)',
                self::DIVISIONS_LON
            )
            ->addArgument(
                'offsetLat',
                InputArgument::OPTIONAL,
                'offset lat sector(0 default)',
                0
            )
            ->addArgument(
                'offsetLon',
                InputArgument::OPTIONAL,
                'offset lat sector(0 default)',
                0
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();
        $this->em = $em;
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
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


//        $output->writeln('Generating sectors...');
//        $sectors = $this->getSectors($input->getArgument('latDiv'), $input->getArgument('lonDiv'));
//        $output->writeln('...' . count($sectors) . ' sectors generated');

        $latDiv = $input->getArgument('latDiv');
        $lonDiv = $input->getArgument('lonDiv');
        $numSectors = $latDiv * $lonDiv;
        $output->writeln('Sectors: '.$numSectors);

        $output->writeln('Generating heatpoints...');
        $heatPoints = array();

        //foreach ($sectors as $key => $sector) {
        $ids = array();
        $k = 0;
        for ($i = $input->getArgument('offsetLat'); $i < $latDiv; $i++) {
            for ($j = $input->getArgument('offsetLon'); $j < $lonDiv; $j++) {
                $sector = $this->calculateSector($i, $j, $latDiv, $lonDiv);

                $points = $this->findPointsInbound($sector[1], $sector[3]);
                $heatPoint = $this->calculateHeatPoint($points, $sector);
                if ($heatPoint) {
                    $this->markAsCalculated($points);
                    $output->writeln('...on sector ('.$i.','.$j.')');
                    $output->writeln('...' . count($points) . ' points found inbound');
                    $heatPoints[] = $heatPoint;
                    $hp = $this->addHeatpoint($heatPoint);
                    $this->em->persist($hp);
                    $this->em->flush();
                    $output->writeln('...[' . $heatPoint['lat'] . ',' . $heatPoint['lon'] . ',' . $heatPoint['force'] . '] heatpoint generated');
                } else {
                    //                $output->writeln('...no points found');
                }

                $k++;

                if ($k % 100000 == 0) {
                    $output->writeln('[#'.$k.' ('.$i.','.$j.') checkpoint & flush | last sector scanned was ('.$sector[1][0].','.$sector[1][1].')('.$sector[3][0].','.$sector[3][1].')]');
//                    $this->em->flush();
//                    $this->em->clear();
                }

                unset($points);
                unset($heatPoint);
            }
        }
        $this->em->flush();
    }

    private function calculateSector($sectorLat, $sectorLon, $latDiv, $lonDiv)
    {
        $latBounds = array(-90, 90);
        $latRange = abs($latBounds[0]) + $latBounds[1];
        $lonBounds = array(-180, 180);
        $lonRange = abs($lonBounds[0]) + abs($lonBounds[1]);

        $latInc = $latRange / $latDiv;
        $lonInc = $lonRange / $lonDiv;

        $latCurr = ($latInc * $sectorLat) - abs($latBounds[0]);
        $lonCurr = ($lonInc * $sectorLon) - abs($lonBounds[0]);

        return array(
            array($latCurr + $latInc, $lonCurr),
            array($latCurr, $lonCurr),
            array($latCurr, $lonCurr + $lonInc),
            array($latCurr + $latInc, $lonCurr + $lonInc)
        );
    }

    private function markAsCalculated($points)
    {
        if (count($points)==0) {
            return false;
        }

        $ids = array();
        foreach ($points as $point) {
            $ids[] = $point['id'];
        }

        $plr = $this->em->getRepository('PokemonBundle:PokemonLocation');
        $q = $plr->createQueryBuilder('pl')->update()
            ->set('pl.inHeatmap', 1)
            ->where('pl.id IN (:ids)')
            ->setParameter('ids', implode(",", $ids))
            ->getQuery();

        $q->execute();
        echo implode(",", $ids);
        die();
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
            ->select('pl.id')
            ->where('pl.inHeatmap = :inHeatmap')
            ->andWhere('pl.lat <= :latMax')
            ->andWhere('pl.lon > :lonMin')
            ->andWhere('pl.lon <= :lonMax')
            ->andWhere('pl.lat > :latMin')
            ->setParameters(array(
                'latMin' => $latlon1[0],
                'latMax' => $latlon2[0],
                'lonMin' => $latlon1[1],
                'lonMax' => $latlon2[1],
                'inHeatmap' => 0
            ))
            ->getQuery();

        $results = $query->getArrayResult();

        return $results;
    }

    private function calculateHeatPoint($points, $sector)
    {
        $heatPoint = array();

        /** @var PokemonLocation $point */
//        foreach ($points as $point) {
//            $point->setInHeatmap(true);
//            $this->em->persist($point);
//        }

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