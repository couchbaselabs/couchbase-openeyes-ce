<?php

class PincodeHelper
{
    public static function generatePincode()
    {
        $temp_pincode = sprintf("%06d", mt_rand(0, 999999));
        
        while(!self::validatePincode($temp_pincode))
        {
            $temp_pincode = sprintf("%06d", mt_rand(0, 999999));
        }
        return $temp_pincode;
    }

    private static function validatePincode($temp_pincode)
    {
        return !self::isPreviouslyUsed($temp_pincode) && !self::isSequentialNum($temp_pincode) && !self::isRepeatNum($temp_pincode) && !self::isSecretaryPin($temp_pincode);
    }

    private static function isPreviouslyUsed($pin)
    {
        // Try to query all current pincodes and check in PHP
        // This avoids Couchbase N1QL parameter binding issues
        try {
            $pincodes = Yii::app()->cbdb->createCommand()
                ->select('pincode')
                ->from('user_pincode')
                ->queryAll();
            
            // Check if the pin is in the current pincodes
            if (is_array($pincodes)) {
                foreach ($pincodes as $row) {
                    if (isset($row['pincode']) && $row['pincode'] == $pin) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            Yii::log('Error querying user_pincode (attempting fallback): ' . $e->getMessage(), CLogger::LEVEL_WARNING);
            // Continue to version check if available
        }
        
        // Try to query user_pincode_version for recently used pincodes (last 12 months)
        // This is optional as it depends on whether version table exists
        try {
            // Calculate date 12 months ago
            $twelveMonthsAgo = date('Y-m-d H:i:s', strtotime('-12 months'));
            
            $versionedPincodes = Yii::app()->cbdb->createCommand()
                ->select('pincode, version_date')
                ->from('user_pincode_version')
                ->queryAll();
            
            // Check if the pin is in the versioned pincodes and is recent
            if (is_array($versionedPincodes)) {
                foreach ($versionedPincodes as $row) {
                    if (isset($row['pincode']) && $row['pincode'] == $pin && 
                        isset($row['version_date']) && strtotime($row['version_date']) > strtotime($twelveMonthsAgo)) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            // If user_pincode_version doesn't exist, continue without version check
            Yii::log('Note: user_pincode_version query failed (optional): ' . $e->getMessage(), CLogger::LEVEL_WARNING);
        }
        
        return false;
    }

    private static function isSequentialNum($pin) {
		$count = 1;

		for ($i = 0; $i < strlen($pin); $i++) {
			if ((substr($pin, $i, 1) + 1) == substr($pin, $i + 1, 1)) {
				$count++;
			}
		}

        return $count === strlen($pin);
	}

	private static function isRepeatNum($pin) {
		return preg_match('/(\d)\1{5}/', $pin);
	}

    private static function isSecretaryPin($pin){
        if(SettingMetadata::model()->getSetting("secretary_pin")){
            return $pin === SettingMetadata::model()->getSetting("secretary_pin");
        }
        return false;
    }
}