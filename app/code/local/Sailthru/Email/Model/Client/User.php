<?php
/**
 * Client User Model
 *
 * @category  Sailthru
 * @package   Sailthru_Email
 *
 */
class Sailthru_Email_Model_Client_User extends Sailthru_Email_Model_Client
{

    public function  __construct()
    {
        parent::__construct();
    }
    /**
     * Get customer data object
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return array
     */
    public function getCustomerData(Mage_Customer_Model_Customer $customer)
    {
        try {
            if ($primaryBillingAddress = $customer->getPrimaryBillingAddress()) {
                $address = implode(', ',$primaryBillingAddress->getStreet());
                $state = $customer->getPrimaryBillingAddress()->getRegion();
                $zipcode = $customer->getPrimaryBillingAddress()->getPostcode();
            } else {
                $address = '';
                $state= '';
                $zipcode= '';
            }


            $data = array(
                'id' => $customer->getEmail(),
                'key' => 'email',
                'fields' => array('keys' => 1),
                'keysconfict' => 'merge',
                'vars' => array(
                    'id' => $customer->getId(),
                    'name' => $customer->getName(),
                    'suffix' => $customer->getSuffix(),
                    'prefix' => $customer->getPrefix(),
                    'firstName' => $customer->getFirstname(),
                    'middleName' => $customer->getMiddlename(),
                    'lastName' => $customer->getLastname(),
                    'address' => $address,
                    //'attributes' => $customer->getAttributes(),
                    'storeID' => $customer->getStoreId(),
                    //'websiteId' => $customer->getWebsiteStoreId(),
                    'groupId' => $customer->getGroupId(),
                    'taxClassId' => $customer->getTaxClassId(),
                    'createdAt' => date("Y-m-d H:i:s", $customer->getCreatedAtTimestamp()),
                    'primaryBillingAddress' => $this->getAddress($customer->getPrimaryBillingAddress()),
                    'defaultBillingAddress' => $this->getAddress($customer->getDefaultBillingAddress()),
                    'defaultShippingAddress' => $this->getAddress($customer->getDefaultShippingAddress()),
                    'state' => $state,
                    'zipCode' => $zipcode
                 ),
                //Feel free to modify the lists below to suit your needs
                //You can read up documentation on http://getstarted.sailthru.com/api/user
                'lists' => array(Mage::helper('sailthruemail')->getMasterList() => 1)
            );
            return $data;
        } catch(Exception $e) {
            Mage::logException($e);
        }
    }

    public function getAddress($address)
    {
        if ($address) {
            return array(
                'firstname' => $address->getFirstname(),
                'middlename' => $address->getMiddlename(),
                'lastname' => $address->getLastname(),
                'company' => $address->getCompany(),
                'city' => $address->getCity(),
                 'address' => implode(', ',$address->getStreet()),
                'country' => $address->getCountryId(),
                'state' => $address->getRegion(),
                'postcode' => $address->getPostcode(),
                'telephone' => $address->getTelephone(),
                'fax' => $address->getFax()
            );
        }
    }

    /**
     * Send customer data through API
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return array
     */
    public function sendCustomerData(Mage_Customer_Model_Customer $customer)
    {
        try {
            $data = $this->getCustomerData($customer);
            $response = $this->apiPost('user', $data);
            $this->setCookie($response);
        } catch(Sailthru_Email_Model_Client_Exception $e) {
             Mage::logException($e);
        } catch(Exception $e) {
            Mage::logException($e);
        }
        return $this;
    }

    public function sendSubscriberData($subscriber)
    {
        try {
            $data = array('id' => $subscriber->getSubscriberEmail(),
                'key' => 'email',
                'keys' => array('email' => $subscriber->getSubscriberEmail()),
                'keysconflict' => 'merge',
                 //this should be improved. Check to see if list is set.  Allow multiple lists.
                'lists' => array(Mage::helper('sailthruemail')->getNewsletterList() => 1),
                'vars' => array('subscriberId' => $subscriber->getSubscriberId(),
                    'status' => $subscriber->getSubscriberStatus(),
                    'Website' => Mage::app()->getStore()->getWebsiteId(),
                    'Store' => Mage::app()->getStore()->getName(),
                    'Store Code' => Mage::app()->getStore()->getCode(),
                    'Store Id' => Mage::app()->getStore()->getId(),
                    'fullName' => $subscriber->getSubscriberFullName(),
                ),
                'fields' => array('keys' => 1),
                //Hacky way to prevent user from getting campaigns if they are not subscribed
                //An example is when a user has to confirm email before before getting newsletters.
                'optout_email' => ($subscriber->getSubscriberStatus() != 1) ? 'blast' : 'none',
            );
            $response = $this->apiPost('user', $data);
            $sailthru_hid = $response['keys']['cookie'];
            $cookie = Mage::getSingleton('core/cookie')->set('sailthru_hid', $sailthru_hid);
        } catch(Exception $e) {
            Mage::logException($e);
        }
        return $this;
    }

    public function setCookie($response)
    {
        if (array_key_exists('ok',$response) && array_key_exists('keys',$response)) {
            $cookie = Mage::getSingleton('core/cookie')->set('sailthru_hid', $response['keys']['cookie']);
            return true;
        } else {
            throw new Sailthru_Email_Model_Client_Exception('Response: ' . json_encode($response));
        }

    }

    public function login($email)
    {
        try {
            $data = array(
                    'id' => $email,
                    'key' => 'email',
                    'fields' => array('keys' => 1)
            );
            $response = $this->apiGet('user', $data);
            return $this->setCookie($response);
        } catch(Sailthru_Email_Model_Client_Exception $e) {
            Mage::logException($e);
        } catch(Exception $e) {
            Mage::logException($e);
        }
    }


    public function logout()
    {
        try {
            $cookie = Mage::getSingleton('core/cookie')->delete('sailthru_hid');
            return true;
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }

}