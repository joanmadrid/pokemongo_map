<?php

namespace PokemonBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PokemonLocation
 *
 * @ORM\Table(name="pokemon_location")
 * @ORM\Entity(repositoryClass="PokemonBundle\Repository\PokemonLocationRepository")
 */
class PokemonLocation
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var float
     *
     * @ORM\Column(name="lat", type="float")
     */
    private $lat;

    /**
     * @var float
     *
     * @ORM\Column(name="lon", type="float")
     */
    private $lon;

    /**
     * @ORM\ManyToOne(targetEntity="Pokemon", inversedBy="pokemonLocations")
     * @ORM\JoinColumn(name="pokemon_id", referencedColumnName="id")
     */
    private $pokemon;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set lat
     *
     * @param float $lat
     * @return PokemonLocation
     */
    public function setLat($lat)
    {
        $this->lat = $lat;

        return $this;
    }

    /**
     * Get lat
     *
     * @return float 
     */
    public function getLat()
    {
        return $this->lat;
    }

    /**
     * Set lon
     *
     * @param float $lon
     * @return PokemonLocation
     */
    public function setLon($lon)
    {
        $this->lon = $lon;

        return $this;
    }

    /**
     * Get lon
     *
     * @return float 
     */
    public function getLon()
    {
        return $this->lon;
    }

    /**
     * @return Pokemon
     */
    public function getPokemon()
    {
        return $this->pokemon;
    }

    /**
     * @param Pokemon $pokemon
     */
    public function setPokemon($pokemon)
    {
        $this->pokemon = $pokemon;
    }
}
