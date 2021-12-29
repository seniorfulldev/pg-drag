<?php

namespace Drag;

use Atmospherics\Atmospherics;
use Conversions\Conversions;

class Drag
{

    /**
     * Create a new class instance.
     *

     * @return void
     */
    public function __construct()
    {
        $string = file_get_contents(__DIR__ . "/ingals.data.json");
        $this->gravity = 32.176; // Feet Per Second Per Second
        $this->conversions = new Conversions();
        $this->atmospherics = new Atmospherics();
        $this->ingals = json_decode($string, true);
    }

    public function clicksToReachMaximumPointBlankRangeZero($ballisticCoefficient, $scopeHeightInches, $scopeElevationClicksPerMOA, $maximumOrdinate, $muzzleVelocityFPS, $muzzleAngleDegrees)
    {
        // Calculates how many up or down clicks to adjust the scope to change from the current zero to the maximum point blank range zero.  Maximum point blank range is the maximum range at
        //     which the user can shoot, without holdover or scope adjustment, while not exceeding a pre-determined maximum ordinate (target radius).
        // Calculate the range you need to zero the rifle to obtain a maximum point blank range
        $maxPointBlankRangeZeroYards = $this->maximumPointBlankRangeZero($ballisticCoefficient, $muzzleVelocityFPS, $maximumOrdinate);
        // Calculate the velocity (feet per second) of the bullet at the new zero
        $velocityAtMaxPointBlankRangeZero = $this->velocityFromRange($ballisticCoefficient, $muzzleVelocityFPS, $maxPointBlankRangeZeroYards);
        // Calculate the time (seconds) of flight of the bullet at the new velocity
        $timeAtMaxPointBlankRangeZero = $this->time($ballisticCoefficient, $muzzleVelocityFPS, $velocityAtMaxPointBlankRangeZero);
        // Calculate the drop (inches) of the bullet at the new time and velocity
        $dropAtMaxPointBlankRangeZero = $this->drop($muzzleVelocityFPS, $velocityAtMaxPointBlankRangeZero, $timeAtMaxPointBlankRangeZero);
        // Calculate the vertical position (inches) of the bullet at the given drop
        $verticalPositionAtMaxPointBlankRangeZero = (-$scopeHeightInches / 12) + (($dropAtMaxPointBlankRangeZero / 12) + ($maxPointBlankRangeZeroYards * 3) * tan($this->conversions->degreesToRadians($muzzleAngleDegrees))) * 12;
        // Calculate the number of scope clicks needed to correct the above calculated vertical position making the new vertical position zero.
        return -($this->conversions->inchesToMinutesOfAngle($verticalPositionAtMaxPointBlankRangeZero, $maxPointBlankRangeZeroYards) * $scopeElevationClicksPerMOA);

    }

    public function crossWindDrift($currentRangeYards, $currentTimeSeconds, $crossWindAngleDegrees, $crossWindVelocityMPH, $muzzleAngleDegrees, $muzzleVelocityFPS)
    {
        // Calculates how far the bullet drifts (inches) due to wind.
        // $conversions = new Conversions();
        return (sin($this->conversions->degreesToRadians($crossWindAngleDegrees)) * $this->conversions->milesPerHourToInchesPerSecond($crossWindVelocityMPH) / 12 * ($currentTimeSeconds - ($currentRangeYards * 3) / ($muzzleVelocityFPS * cos($this->conversions->degreesToRadians($muzzleAngleDegrees))))) * 12;
    }

    public function drop($muzzleVelocityFPS, $currentVelocityFPS, $currentTimeSeconds)
    {
        // Calculates how far the bullet falls (inches) due to gravity, if their were no angle at the muzzle.
        $falls = $this->atmospherics->dropTable[floor(($currentVelocityFPS / $muzzleVelocityFPS) * 100 + 0.5)];
        return -($falls * pow($currentTimeSeconds, 2));
    }

    public function energy($bulletWeightGrains, $currentVelocityFPS)
    {
        // Calculates the kinetic energy (foot pounds) retained in the bullet.
        return $bulletWeightGrains * pow($currentVelocityFPS, 2) / ($this->gravity * 7000 * 2);
    }

    public function ingalsSpaceFromVelocity($currentVelocity)
    {
        // Returns the space value from the Ingals table at the given velocity.
        $counter = 0;
        while ($this->ingals['v'][$counter] > $currentVelocity) {
            $counter++;
            // echo $counter;
        }
        // $spaceFromVelocity;
        if ($this->ingals['v'][$counter] === $currentVelocity) {
            $spaceFromVelocity = $this->ingals['s'][$counter];
        } else {
            // Interoperlate Array
            $differenceBetweenVelocityIndexes = $this->ingals['v'][$counter - 1] - $this->ingals['v'][$counter];
            $distanceFromVelocityIndex = $currentVelocity - $this->ingals['v'][$counter];
            $differenceBetweenSpaceIndexes = $this->ingals['s'][$counter] - $this->ingals['s'][$counter - 1];
            $percentage = $distanceFromVelocityIndex / $differenceBetweenVelocityIndexes;
            $spaceFromVelocity = $this->ingals['s'][$counter] - ($differenceBetweenSpaceIndexes * $percentage);
        }
        return $spaceFromVelocity;
    }

    public function ingalsTimeFromVelocity($currentVelocity)
    {
        // Returns the Time value from the Ingals table at the given Velocity.
        $counter = 0;
        while ($this->ingals['v'][$counter] > $currentVelocity) {
            if ($counter === count($this->ingals['v'])-1){
                break;
            }
            $counter++;
        }
        if ($this->ingals['v'][$counter] === $currentVelocity) {
            $timeFromVelocity = $this->ingals['t'][$counter];
        } else {
            // Interoperlate Array
            $differenceBetweenVelocityIndexes = $this->ingals['v'][$counter - 1] - $this->ingals['v'][$counter];
            $distanceFromVelocityIndex = $currentVelocity - $this->ingals['v'][$counter];
            $differenceBetweenSpaceIndexes = $this->ingals['t'][$counter] - $this->ingals['t'][$counter - 1];
            $percentage = $distanceFromVelocityIndex / $differenceBetweenVelocityIndexes;
            $timeFromVelocity = $this->ingals['t'][$counter] - ($differenceBetweenSpaceIndexes * $percentage);
        }
        return $timeFromVelocity;
    }

    public function ingalsVelocityFromSpace($currentSpace)
    {
        // Returns the Velocity value from the Ingals table at the given Space.
        $counter = 0;
        while ($this->ingals['s'][$counter] < $currentSpace) {
            $counter++;
            if ($counter === count($this->ingals['s'])-1){
                break;
            }
        }
        if ($this->ingals['s'][$counter] === $currentSpace) {
            $velocityFromSpace = $this->ingals['v'][$counter];
        } else {
            // Interoperlate Array
            $differenceBetweenSpaceIndexes = $this->ingals['s'][$counter] - $this->ingals['s'][$counter - 1];
            $distanceFromSpaceIndex = $this->ingals['s'][$counter] - $currentSpace;
            $differenceBetweenVelocityIndexes = $this->ingals['v'][$counter - 1] - $this->ingals['v'][$counter];
            $percentage = $distanceFromSpaceIndex / $differenceBetweenSpaceIndexes;
            $velocityFromSpace = $this->ingals['v'][$counter] + ($differenceBetweenVelocityIndexes * $percentage);
        }
        return $velocityFromSpace;
    }

    public function ingalsVelocityFromTime($currentTime)
    {
        // Returns the Velocity value from the Ingals table at the given Time.
        $counter = 0;
        while ($this->ingals['t'][$counter] < $currentTime) {
            $counter++;
        }
        if ($this->ingals['t'][$counter] === $currentTime) {
            $velocityFromTime = $this->ingals['v'][$counter];
        } else {
            // Interoperlate Array
            $differenceBetweenTimeIndexes = $this->ingals['t'][$counter] - $this->ingals['t'][$counter - 1];
            $distanceFromTimeIndex = $this->ingals['t'][$counter] - $currentTime;
            $differenceBetweenVelocityIndexes = $this->ingals['v'][$counter - 1] - $this->ingals['v'][$counter];
            $percentage = $distanceFromTimeIndex / $differenceBetweenTimeIndexes;
            $velocityFromTime = $this->ingals['v'][$counter] + ($differenceBetweenVelocityIndexes * $percentage);
        }
        return $velocityFromTime;
    }

    public function lead($targetSpeedMPH, $currentTimeSeconds)
    {
        // Calculates how far the user needs to lead (inches) a moving target.
        return $this->conversions->milesPerHourToInchesPerSecond($targetSpeedMPH) * $currentTimeSeconds;
    }

    public function maximumPointBlankRange($ballisticCoefficient, $muzzleVelocityFPS, $maximumOrdinate)
    {
        // Calculate the maximum range at which the user can shoot, without holdover or scope adjustment, while not exceeding a pre-determined maximum ordinate (target radius).
        // Time (seconds)it takes to reach the range having a maximum ordinate supplied above
        $timeToMaximumOrdinate = 0.25 * pow($maximumOrdinate / 3, 0.5);
        // Velocity (feet per second) of the bullet at the above calculated time
        $velocityAtTimeToMaximumOrdinate = $this->velocityFromTime($ballisticCoefficient, $muzzleVelocityFPS, $timeToMaximumOrdinate);
        // Drop (inches) of the bullet at the above given time and velocity***
        $dropAtMaximumPointBlankRangeZero = $this->drop($muzzleVelocityFPS, $velocityAtTimeToMaximumOrdinate, $timeToMaximumOrdinate);
        // The bullet may drop the radius of the target below zero at the true maximum point blank range
        $dropAtMaximumPointBlankRange = $dropAtMaximumPointBlankRangeZero - $maximumOrdinate;
        // Loop through dropping velocity until Drop = DropAtMaximumPointBlankRange to find the velocity at the true point blank range
        $velocityAtMaximumPointBlankRange = $velocityAtTimeToMaximumOrdinate;
        while ($this->drop($muzzleVelocityFPS, $velocityAtMaximumPointBlankRange, $this->time($ballisticCoefficient, $muzzleVelocityFPS, $velocityAtMaximumPointBlankRange)) > $dropAtMaximumPointBlankRange) {
            $velocityAtMaximumPointBlankRange -= 0.1;
        }
        // Given the velocity at the point blank range, calculate the actual range
        return $this->range($ballisticCoefficient, $muzzleVelocityFPS, $velocityAtMaximumPointBlankRange);
    }

    public function maximumPointBlankRangeZero($ballisticCoefficient, $muzzleVelocityFPS, $maximumOrdinate)
    {
        // Maximum Point Blank Range Zero (yards) is the range that the user should zero his/her rifle to obtain their maximum point blank range.
        // This range allows a user to shoot, without holdover or scope adjustment, while not exceeding a pre-determined maximum ordinate (target radius).
        // Time (seconds)it takes to reach the range having a maximum ordinate supplied above
        $timeToMaximumOrdinate = 0.25 * pow($maximumOrdinate / 3, 0.5);
        // Velocity (feet per second) of the bullet at the above calculated time
        $velocityAtTimeToMaximumOrdinate = $this->velocityFromTime($ballisticCoefficient, $muzzleVelocityFPS, $timeToMaximumOrdinate);
        // Given the velocity at the point blank range zero, calculate the actual range to zero the rifle
        return $this->range($ballisticCoefficient, $muzzleVelocityFPS, $velocityAtTimeToMaximumOrdinate);
    }

    public function modifiedBallisticCoefficient($ballisticCoefficient, $altitudeFeet, $temperatureFahrenheit, $barometricPressureInchesHg, $relativeHumidityPercent)
    {
        // Takes the bullets ballistic coefficient at standard atmospheric conditions (sea level), and converts it to a new ballistic coefficient at the current altitudeFeet and conditions.
        $altitudeAdjustmentFactor = $this->atmospherics->altitudeAdjustmentFactor($altitudeFeet);
        $temperatureAdjustmentFactor = $this->atmospherics->temperatureAdjustmentFactor($altitudeFeet, $temperatureFahrenheit);
        $barometricPressureAdjustmentFactor = $this->atmospherics->barometricPressureAdjustmentFactor($altitudeFeet, $barometricPressureInchesHg);
        $relativeHumidityAdjustmentFactor = $this->atmospherics->relativeHumidityAdjustmentFactor($temperatureFahrenheit, $barometricPressureInchesHg, $relativeHumidityPercent / 100);
        return $ballisticCoefficient * ($altitudeAdjustmentFactor * (1 + $temperatureAdjustmentFactor - $barometricPressureAdjustmentFactor) * $relativeHumidityAdjustmentFactor);
    }

    public function muzzleAngleDegreesForZeroRange($muzzleVelocityFPS, $zeroRangeYards, $scopeHeightInches, $ballisticCoefficient)
    {
        // Calculates the neccessary angle (degrees) of the muzzle to obtain the requested zero range.
        // This is done by looping through vertical position with different muzzle angles at the given range until a muzzle angle is found that produces a vertical position of 0.
        $velocityAtZeroRange = $this->velocityFromRange($ballisticCoefficient, $muzzleVelocityFPS, $zeroRangeYards);
        $timeAtZeroRange = $this->time($ballisticCoefficient, $muzzleVelocityFPS, $velocityAtZeroRange);
        $dropAtZeroRange = $this->drop($muzzleVelocityFPS, $velocityAtZeroRange, $timeAtZeroRange);
        $muzzleAngleDegreesForZeroRange = 0;
        while ($this->verticalPosition($scopeHeightInches, $muzzleAngleDegreesForZeroRange, $zeroRangeYards, $dropAtZeroRange) < 0) {
            $muzzleAngleDegreesForZeroRange += 0.00001;
        }
        return $muzzleAngleDegreesForZeroRange;
    }

    public function optimalRiflingTwist($bulletDiameterInches, $bulletLengthInches){
        // Calculates the best rifling twist rate (inches per twist) to stabalize the length of bullet being used.
		return $bulletDiameterInches * 150 / ($bulletLengthInches / $bulletDiameterInches);
    }

    public function range($ballisticCoefficient, $muzzleVelocityFPS, $currentVelocityFPS){
       // Calculates the range (yards) of the bullet at a given velocity.
		return $ballisticCoefficient * ($this->ingalsSpaceFromVelocity($currentVelocityFPS) - $this->ingalsSpaceFromVelocity($muzzleVelocityFPS)) / 3;
	}

    public function rifleRecoilVelocity($bulletWeightGrains, $muzzleVelocityFPS, $powderWeightGrains, $rifleWeightPounds){
        // Calculates the amount of rearward velocity (feet per second) of the rifle upon firing.
		return ($bulletWeightGrains * $muzzleVelocityFPS + $powderWeightGrains * 4000) / ($rifleWeightPounds * 7000);
	}

    public function rifleRecoilEnergy($bulletWeightGrains, $muzzleVelocityFPS, $powderWeightGrains, $rifleWeightPounds){
        // Calculates the amount of rearward force (foot pounds) of the rifle upon firing.
		return $rifleWeightPounds * pow($this->rifleRecoilVelocity($bulletWeightGrains, $muzzleVelocityFPS, $powderWeightGrains, $rifleWeightPounds), 2) / ($this->gravity * 2);
	}

    public function sectionalDensity($bulletWeightGrains, $bulletDiameterInches){
        // Calculates the mass per given diameter of the bullet.  Used in determining form factor.
		return $bulletWeightGrains / (7000 * pow($bulletDiameterInches, 2));
    }

    public function time($ballisticCoefficient, $muzzleVelocityFPS, $currentVelocityFPS){
       // Calculates the amount of time (seconds) it takes the bullet to slow from the initial velocity to a specific lower velocity.
		return $ballisticCoefficient * ($this->ingalsTimeFromVelocity($currentVelocityFPS) - $this->ingalsTimeFromVelocity($muzzleVelocityFPS));
	}

    public function velocityFromRange($ballisticCoefficient, $muzzleVelocityFPS, $currentRangeYards){
        // Calculates the velocity (feet per second) remaining in the bullet at a given range (yards).
		$currentSpace = $this->ingalsSpaceFromVelocity($muzzleVelocityFPS) + (($currentRangeYards * 3) / $ballisticCoefficient);
		return $this->ingalsVelocityFromSpace($currentSpace);
	}

    public function velocityFromTime($ballisticCoefficient, $muzzleVelocityFPS, $currentTimeSeconds){
        // Calculates the velocity (feet per second) remaining in the bullet at a given time (seconds).
		return $this->ingalsVelocityFromTime($currentTimeSeconds / $ballisticCoefficient + $this->ingalsTimeFromVelocity($muzzleVelocityFPS));
	}

    public function verticalPosition($scopeHeightInches, $muzzleAngleDegrees, $currentRangeYards, $currentDropInches){
        // Calculates how far the bullet falls (inches) due to gravity, taking into account the angle of the muzzle.
		return ($currentDropInches+($currentRangeYards*36) * tan($this->conversions->degreesToRadians($muzzleAngleDegrees)))-$scopeHeightInches;
	}

}
