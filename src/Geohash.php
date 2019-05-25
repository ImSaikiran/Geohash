<?php

namespace Sk\Geohash;

/**
 * @author  Saikiran Ch <saikiranchavan@gmail.com>
 * @class   Geohash
 * @description  Algorithm to encode geographic coordinates to a string of letters and digits
 */
class Geohash
{
    /**
     * Used for decoding the hash from base32
     */
    protected $base32Mapping = "0123456789bcdefghjkmnpqrstuvwxyz";

    /**
     * Encode the latitude and longitude into a hashed string
     * @param  float   Latitude
     * @param  float   longitude
     * @param  int     GeohashLength
     * @return string  Hashed string obtained from the coordinates
     */
    public function encode($latitude, $Longitude, $geohashLength = 5)
    {
        // Get latitude and longitude bits length from given geohash Length
        if ($geohashLength % 2 == 0) {
            $latBitsLength = $lonBitsLength = ($geohashLength/2) * 5;
        } else {
            $latBitsLength = (ceil($geohashLength / 2) * 5) - 3;
            $lonBitsLength = $latBitsLength + 1;
        }

        // Convert the coordinates into binary format
        $binaryString = "";
        $latbits = $this->getBits($latitude, -90, 90, $latBitsLength);
        $lonbits = $this->getBits($Longitude, -180, 180, $lonBitsLength);
        $binaryLength = strlen($latbits) + strlen($lonbits);

        // Combine the lat and lon bits and get the binaryString
        for ($i=1 ; $i < $binaryLength + 1; $i++) {
            if ($i%2 == 0) {
                $pos = (int)($i-2)/2;
                $binaryString .= $latbits[$pos];
            } else {
                $pos = (int)floor($i/2);
                $binaryString .= $lonbits[$pos];
            }
        }

        // Convert the binary to hash
        $hash = "";
        for ($i=0; $i< strlen($binaryString); $i+=5) {
            $n = bindec(substr($binaryString,$i,5));
            $hash = $hash . $this->base32Mapping[$n];
        }
        return $hash;
    }

    /**
     * Decode the Geohash into geographic coordinates
     * @param   string  $hash
     * @param   double  Percentage error
     * @return  mixed   Array of Latitude and Longitude
     */
    public function decode($hash, $error = false)
    {
        $hashLength = strlen($hash);
        $latlonbits = base_convert($hash, 32, 2);
        $binaryLength = strlen($latlonbits);
        $latbits = "";
        $lonbits = "";

        $geohashArray = str_split($hash, 1);
        $latlonbits = "";
        foreach($geohashArray as $g) {
            if (($position = stripos($this->base32Mapping, $g)) !== FALSE) {
                $latlonbits .= str_pad(decbin($position), 5, "0", STR_PAD_LEFT);
            } else {
                $latlonbits .= "00000";
            }
        }

        // Even bits take latitude Code
        // Odd bits take longitude Code
        for ($i = 0; $i < $binaryLength; $i++) {
            ($i % 2 == 0) ? ($lonbits .= $latlonbits[$i]) : ($latbits .= $latlonbits[$i]);
        }

        // Get the Coordinates
        $latitude = $this->getCoordinate(-90, 90, $latbits);
        $longitude = $this->getCoordinate(-180, 180, $lonbits);

        // Rounding the latitude and longitude values
        $latitude = round($latitude, $hashLength - 2);
        $longitude = round($longitude, $hashLength - 2);
        return array($latitude, $longitude);
    }

    /**
     * Get the Geographic Coordinate from the binaryString
     * @param  float   $min
     * @param  float   $max
     * @param  string  $binaryString
     * @return float   $coordinate
     */
    public function getCoordinate($min, $max, $binaryString)
    {
        $error = 0;
        for ($i = 0; $i < strlen($binaryString); $i++) {
            $mid = ($min + $max)/2 ;
            if ($binaryString[$i] == 1){
                $min = $mid ;
            } elseif ($binaryString[$i] == 0) {
                $max = $mid;
            }
            $value = ($min + $max)/2;
            $error = $value - $error;
        }
        return $value;
    }

    /**
     * Convert coordinate into binary string according to required bits length
     * @param  float   Coordinate
     * @param  int     minimum value
     * @param  int     maximum value
     * @param  int     bitslength
     * @return string  binary string for the given coordinate
     */
    public function getBits($coordinate, $min, $max, $bitsLength)
    {
        $binaryString = "";
        $i = 0;
        while ($bitsLength > $i) {
            $mid = ($min+$max)/2;
            if ($coordinate > $mid) {
                $binaryString .= "1";
                $min = $mid;
            } else {
                $binaryString .= "0";
                $max = $mid;
            }
            $i++;
        }
        return $binaryString;
    }

  /**
   * Determines adjacent cell in given direction.
   *
   * @param  string  $hash to which adjacent cell is required
   * @param  string  $direction from hash (N/S/E/W)
   * @return string  Geocode of adjacent cell
   */
   public function adjacent($hash, $direction)
   {
        $hash = strtolower($hash);
        $direction = strtolower($direction);

        if (empty($hash) || (strpos('nsew', $direction) === false)){
          return false;
        }

        $neighbour = [
          'n' => [ 'p0r21436x8zb9dcf5h7kjnmqesgutwvy', 'bc01fg45238967deuvhjyznpkmstqrwx' ],
          's' => [ '14365h7k9dcfesgujnmqp0r2twvyx8zb', '238967debc01fg45kmstqrwxuvhjyznp' ],
          'e' => [ 'bc01fg45238967deuvhjyznpkmstqrwx', 'p0r21436x8zb9dcf5h7kjnmqesgutwvy' ],
          'w' => [ '238967debc01fg45kmstqrwxuvhjyznp', '14365h7k9dcfesgujnmqp0r2twvyx8zb' ]
        ];

        $border = [
          'n' => [ 'prxz', 'bcfguvyz' ],
          's' => [ '028b', '0145hjnp' ],
          'e' => [ 'bcfguvyz', 'prxz' ],
          'w' => [ '0145hjnp', '028b' ]
        ];

        $lastCh = substr($hash, -1);
        $parent = substr($hash, 0, -1);

        $type = (strlen($hash) % 2== 0) ? 1 : 0;

        // Check for edge-cases which do not share a common prefix
        if ((strpos($border[$direction][$type], $lastCh) !== false) && !empty($parent)) {
            $parent = $this->adjacent($parent, $direction);
        }

        // Append letter for direction to parent
        return $parent . $this->$base32Mapping[strpos($neighbour[$direction][$type], $lastCh)];
    }

    /**
    * Returns all 8 adjacent cells of the specified geohash.
    *
    * @param string  $hash - the Geohash that would like to meet the neighbours
    * @return array  of the neighbourhood association {n,ne,e,se,s,sw,w,nw => Geohash}
    */
    public function neighbours($hash)
    {
        return [
            'n'  => $this->adjacent($hash, 'n'),
            'ne' => $this->adjacent($this->adjacent($hash, 'n'), 'e'),
            'e'  => $this->adjacent($hash, 'e'),
            'se' => $this->adjacent($this->adjacent($hash, 's'), 'e'),
            's'  => $this->adjacent($hash, 's'),
            'sw' => $this->adjacent($this->adjacent($hash, 's'), 'w'),
            'w'  => $this->adjacent($hash, 'w'),
            'nw' => $this->adjacent($this->adjacent($hash, 'n'), 'w')
        ];
    }
}
