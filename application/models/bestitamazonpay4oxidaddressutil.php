<?php
/**
 * This Software is the property of best it GmbH & Co. KG and is protected
 * by copyright law - it is NOT Freeware.
 *
 * Any unauthorized use of this software without a valid license is
 * a violation of the license agreement and will be prosecuted by
 * civil and criminal law.
 *
 * bestitamazonpay4oxidaddressutil.php
 *
 * The bestitAmazonPay4OxidAddressUtil class file.
 *
 * PHP versions 5
 *
 * @category  bestitAmazonPay4Oxid
 * @package   bestitAmazonPay4Oxid
 * @author    best it GmbH & Co. KG - Alexander Schneider <schneider@bestit-online.de>
 * @copyright 2017 best it GmbH & Co. KG
 * @version   GIT: $Id$
 * @link      http://www.bestit-online.de
 */

/**
 * Class bestitAmazonPay4OxidAddressUtil
 */
class bestitAmazonPay4OxidAddressUtil extends bestitAmazonPay4OxidContainer
{
    /**
     * Returns parsed Street name and Street number in array
     *
     * @param string $sString Full address
     *
     * @return string
     */
    protected function _parseSingleAddress($sString)
    {
        preg_match('/\s*([^\d]*[^\d\s])\s*(\d[^\s]*)\s*(.*)/', $sString, $aResult);

        return $aResult;
    }

    /**
     * Parses the amazon address fields.
     *
     * @param \stdClass $oAmazonData
     * @param array     $aResult
     */
    protected function _parseAddressFields($oAmazonData, array &$aResult)
    {
        // Cleanup address fields and store them to an array
        $aAmazonAddresses = array(
            1 => is_string($oAmazonData->AddressLine1) ? trim($oAmazonData->AddressLine1) : '',
            2 => is_string($oAmazonData->AddressLine2) ? trim($oAmazonData->AddressLine2) : '',
            3 => is_string($oAmazonData->AddressLine3) ? trim($oAmazonData->AddressLine3) : ''
        );

        $aReverseOrderCountries = array('DE', 'AT');
        $aMap = array_flip($aReverseOrderCountries);
        $aCheckOrder = isset($aMap[$oAmazonData->CountryCode]) === true ? array (2, 1) : array(1, 2);
        $sStreet = '';
        $sCompany = '';

        foreach ($aCheckOrder as $iCheck) {
            if ($aAmazonAddresses[$iCheck] !== '') {
                if ($sStreet !== '') {
                    $sCompany = $aAmazonAddresses[$iCheck];
                    break;
                }

                $sStreet = $aAmazonAddresses[$iCheck];
            }
        }

        if ($aAmazonAddresses[3] !== '') {
            $sCompany = ($sCompany === '') ? $aAmazonAddresses[3] : "{$sCompany}, {$aAmazonAddresses[3]}";
        }

        $aResult['CompanyName'] = $sCompany;

        $aAddress = $this->_parseSingleAddress($sStreet);
        $aResult['Street'] = isset($aAddress[1]) === true ? $aAddress[1] : '';
        $aResult['StreetNr'] = isset($aAddress[2]) === true ? $aAddress[2] : '';
        $aResult['AddInfo'] = isset($aAddress[3]) === true ? $aAddress[3] : '';
    }

    /**
     * Returns Parsed address from Amazon by specific rules
     *
     * @param object $oAmazonData Address object
     *
     * @return array Parsed Address
     * @throws oxConnectionException
     */
    public function parseAmazonAddress($oAmazonData)
    {
        //Cast to array
        $aResult = (array)$oAmazonData;

        //Parsing first and last names
        $aFullName = explode(' ', trim($oAmazonData->Name));
        $aResult['LastName'] = array_pop($aFullName);
        $aResult['FirstName'] = implode(' ', $aFullName);

        $sTable = getViewName('oxcountry');
        $oAmazonData->CountryCode = (string)$oAmazonData->CountryCode === 'UK' ? 'GB' : $oAmazonData->CountryCode;
        $sSql = "SELECT OXID
            FROM {$sTable}
            WHERE OXISOALPHA2 = ".$this->getDatabase()->quote($oAmazonData->CountryCode);

        //Country ID
        $aResult['CountryId'] = $this->getDatabase()->getOne($sSql);

        //Parsing address
        $this->_parseAddressFields($oAmazonData, $aResult);

        //If shop runs in non UTF-8 mode encode values to ANSI
        if ($this->getConfig()->isUtf() === false) {
            foreach ($aResult as $sKey => $sValue) {
                $aResult[$sKey] = $this->encodeString($sValue);
            }
        }

        return $aResult;
    }


    /**
     * If shop is using non-Utf8 chars, encode string according used encoding
     *
     * @param string $sString the string to encode
     *
     * @return string encoded string
     */
    public function encodeString($sString)
    {
        //If shop is running in UTF-8 nothing to do here
        if ($this->getConfig()->isUtf() === true) {
            return $sString;
        }

        $sShopEncoding = $this->getLanguage()->translateString('charset');
        return iconv('UTF-8', $sShopEncoding, $sString);
    }
}
