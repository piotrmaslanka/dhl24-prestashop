<?php
	
	class DHL24_webapi_client extends SoapClient
	{
		const WSDL = 'https://testowy.dhl24.com.pl/webapi';

		private $authData;
                private $SAPnumber;
		
		public function __construct()
		{
			parent::__construct( self::WSDL, array('trace' => 1) );
			
			$this->authData=new AuthData();	

			$this->authData->username=Configuration::get('dhl24shipping_e_webapi_login');
			$this->authData->password=Configuration::get('dhl24shipping_e_webapi_password');
            $this->SAPnumber=Configuration::get('dhl24shipping_e_webapi_sap');
		}
		
		public function createShipments($recv_city, $recv_contact_email, $recv_contact_person,
										$recv_contact_phone, $recv_house_number, $recv_apart_number,
										$recv_name, $recv_postal_code, $recv_street,
										
										$czy_za_pobraniem,
										$comment, $content
			)
			{
				
			if ($recv_house_number == NULL) $recv_house_number = '';
			if ($recv_apart_number == NULL) $recv_apart_number = '';	
			
			$shipment=new ShipmentFullData();
			
			$shipment->shipper->apartmentNumber=Configuration::get('dhl24shipping_e_apart_number');
            $shipment->shipper->city=Configuration::get('dhl24shipping_e_city');
            $shipment->shipper->contactEmail=Configuration::get('dhl24shipping_e_cemail');
            $shipment->shipper->contactPerson=Configuration::get('dhl24shipping_e_cperson');
            $shipment->shipper->contactPhone=str_replace('-', '', Configuration::get('dhl24shipping_e_cphone'));
            $shipment->shipper->houseNumber=Configuration::get('dhl24shipping_e_house_number');
            $shipment->shipper->name=Configuration::get('dhl24shipping_e_name');
            $shipment->shipper->postalCode=str_replace('-', '', Configuration::get('dhl24shipping_e_postal'));
            $shipment->shipper->street=Configuration::get('dhl24shipping_e_street');
                        
            if ($shipment->shipper->houseNumber == NULL) $shipment->shipper->houseNumber = ''; 
            if ($shipment->shipper->apartmentNumber == NULL) $shipment->shipper->apartmentNumber = '';
            
            $shipment->receiver->apartmentNumber=$recv_apart_number;
            $shipment->receiver->city=$recv_city;
            $shipment->receiver->contactEmail=$recv_contact_email;
            $shipment->receiver->contactPerson=$recv_contact_person;
            $shipment->receiver->contactPhone=str_replace('-', '', $recv_contact_phone);
            $shipment->receiver->houseNumber=$recv_house_number;
            $shipment->receiver->name=$recv_name;
            $shipment->receiver->postalCode=str_replace('-', '', $recv_postal_code);
            $shipment->receiver->street=$recv_street;
                        
            $pd=new PieceDefinition();
            $pd->type="PACKAGE";

           	$pd->height=50;
            $pd->width=60;
            $pd->length=120;
            $pd->weight=30;

            $pd->quantity=1;
            $shipment->pieceList[]=$pd;

/*            if ($czy_za_pobraniem) {
            	$shipment->payment->paymentMethod="CASH";
            	$shipment->payment->payerType="SHIPPER";
            } else { */
            	$shipment->payment->paymentMethod="BANK_TRANSFER";
            	$shipment->payment->payerType="SHIPPER";
//            }

            $shipment->payment->accountNumber=$this->SAPnumber;                        
            $shipment->service->product='AH';
                        
			$date_raw=new DateTime();
            $date=new DateTime($date_raw->format("Y-m-d"));
            if($date_raw->format("w")==6)
            {
                $date->modify('+2 day');
            }
            else
            {
                if($date_raw->format("w")==0)
                {
                    $date->modify('+1 day');
                }
                else
                {
                    if($date_raw->format("H")>12)
                    {
                        if($date_raw->format("w")==5)
                        {
                           $date->modify('+3 day');
                        }
                        else 
                        {
                           $date->modify('+1 day');
                                    }
                                }
                            }
                        }

            $shipment->shipmentDate=$date->format("Y-m-d");

            $shipment->comment=$comment;                        
            $shipment->content=$content;
                        
            $sh=array();
            $sh[]=$shipment;
                        
			$params = array("authData" => $this->authData, "shipments" => $sh);
			
            try
            {
                $response=$this->__soapCall("createShipments", array($params));                      
                $response2=$this->bookCourier($response->createShipmentsResult->item->shipmentId, $date_raw);

                if(!is_object($response2)) throw new Exception($response2);
            }
                        catch (Exception $e)
                        {
                            $this->deleteShipments(@$response->createShipmentsResult->item->shipmentId);
                            throw new Exception($e->getMessage());
                        }
                        
			return $response;
		}
                
        public function bookCourier($itemId, $date)
        {
            $pickupDate;//string Data w formacie RRRR-MM-DD
            $pickupTimeFrom;//string Godzina, od której przesyłka jest gotowa do odebrania (w formacie GG:MM)
            $pickupTimeTo;//string Godzina, do której można odebrać przesyłkę (w formacie GG:MM)
            $additionalInfo;//string(50)
            $shipmentIdList;//array Tablica elementów typu string, zawierająca identyfikatory przesyłek

        if($date->format("w")==6) 
                    {
                        $date->modify('+2 day'); 
                        $pickupTimeFrom="10:00";
                        $pickupTimeTo="15:00";
                    }
                    else
                    {
                        if($date->format("w")==0)
                        {
                            $date->modify('+1 day');
                            $pickupTimeFrom="10:00";
                            $pickupTimeTo="15:00";
                        }
                        else
                        {
                            if($date->format("H")>12)
                            {
                                if($date->format("w")==5)
                                {
                                    $date->modify('+3 day');
                                }
                                else 
                                {
                                    $date->modify('+1 day');
                                }
                                $pickupTimeFrom="10:00";
                                $pickupTimeTo="15:00";
                            }
                            else
                            {
                                $pickupTimeFrom=$date->format("H:i");
                                $pickupTimeTo="15:00";
                            }
                        }
                    }

            $pickupDate=$date->format("Y-m-d");
            $shipmentIdList=array();
            $shipmentIdList[]=$itemId;
                   
            $params = array
            (
                "authData" => $this->authData,
                "pickupDate" => $pickupDate,
                "pickupTimeFrom" => $pickupTimeFrom,
                "pickupTimeTo" => $pickupTimeTo,
                "shipmentIdList" => $shipmentIdList,
            );

            try
            {
                 $response = $this->__soapCall("bookCourier", array($params));
            }
            catch (Exception $e)
            {
                 return $e->getMessage();
            }

            return $response;
        }
            
            public function deleteShipments($itemId)
            {
                $sh=array();
                $sh[]=$itemId;
                 $params = array
                    (
                        "authData" => $this->authData,
                        "shipments" => $sh,
                    );

                    $response = $this->__soapCall("deleteShipments", array($params));
            }
		
            public function getLabels($id)
            {
            	var_dump($id);
               $ip=array();
               $i=new ItemToPrintResponse();
               $i->shipmentId=$id;
               $i->labelType="LP";
               $ip[]=$i;
               $params = array
               (
                    "authData" => $this->authData,
                    "itemsToPrint" => $ip,
                );

                $response = $this->__soapCall("getLabels", array($params));
                
                return $response;
            }
            
            public function getTrackAndTraceInfo($id)
            {
               
               $params = array
               (
                    "authData" => $this->authData,
                    "shipmentId" => $id,
                );

                $response = $this->__soapCall("getTrackAndTraceInfo", array($params));
                
                return $response;
            }
	}
        
	
    class ItemToPrintResponse
     {
         public $shipmentId;
         public $labelType;	
         public $labelData;
         public $labelMimeType;
     }
      
	class AuthData
	{
		public $username;//string(32)
		public $password;//string(32)
	}
	
	class AddressDHL
	{
		public $name;//string(60)
		public $postalCode;//string(10)
		public $city;//string(17)
		public $street;//string(22)
		public $houseNumber;//string(7) 	
		public $apartmentNumber;//string(7) 	
		public $contactPerson;//string(60)
		public $contactPhone;//string(60)
		public $contactEmail;//string(60)
	}
	
	class PieceDefinition
	{
		public $type;//string Jedna z warto�ci: "ENVELOPE", "PACKAGE", "PALLET"
		public $width;//integer
		public $height;//integer
		public $length;//integer
		public $weight;//integer
		public $quantity;//integer
		public $nonStandard;//boolean
		public $blpPieceId;//string
	}
	
	class PaymentData
	{
		public $paymentMethod;//string Warto�� s�ownikowa: SHIPPER - p�atnik nadawca RECEIVER - p�atnik odbiorca USER - p�atnik trzecia strona
		public $payerType;//string Warto�� s�ownikowa: CASH - got�wka BANK_TRANSFER - przelew
		public $accountNumber;//string(7) 	
		public $costsCenter;//string(20)
	}
	
	class ServiceDefinition
	{
		public $product;//string Warto�� s�ownikowa:AH - przesy�ka krajowa; 09 - Domestic 09; 12 - Domestic 12
		public $deliveryEvening;//boolean
		public $deliverySaturday;//boolean
		public $collectOnDelivery;//boolean
		public $collectOnDeliveryValue;//float maksymalnie 11000 z�
		public $collectOnDeliveryForm;//string Dopuszczalne warto�ci: CASH - got�wka, BANK_TRANSFER - przelew.
		public $collectOnDeliveryReference;//string
		public $insurance;//boolean
		public $insuranceValue;//float
		public $returnOnDelivery;//boolean
		public $returnOnDeliveryReference;//string
		public $proofOfDelivery;//boolean
		public $selfCollect;//boolean
		public $predeliveryInformation;//boolean
		public $preaviso;//boolean
		
	}
	
	class ShipmentFullData
	{
		public $shipper;//class Address
		public $receiver;//class Address
		public $pieceList;//array of class PieceDefinition
		public $payment;//class PaymentData
		public $service;//class Service
		public $shipmentDate;//string
		public $comment;//string(100) 	
		public $content;//string(30)
		public $reference;//string(20) 	
		
		//Tylko odpowied�:
		public $shipmentId;//integer
		public $created;//string
		public $orderStatus;//string
		
		
		public function __construct()
		{
			$this->shipper=new AddressDHL();
			$this->receiver=new AddressDHL();
			$this->pieceList=array();
			$this->payment=new PaymentData();
			$this->service=new ServiceDefinition();
		}		
	}
	
	
	
?>