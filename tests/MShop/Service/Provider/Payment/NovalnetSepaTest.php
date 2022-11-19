<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2022
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class NovalnetSepaTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $ordServItem;
	private $serviceItem;
	private $context;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$serviceManager = \Aimeos\MShop::create( $this->context, 'service' );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->ordServItem = \Aimeos\MShop::create( $this->context, 'order/base/service' )->create();
		$serviceItem = \Aimeos\MShop::create( $this->context, 'service' )->create();
		$serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\NovalnetSepa' )
			->setMethods( ['save', 'getProvider', 'saveRepayData'] )
			->setConstructorArgs( array( $this->context, $serviceItem ) )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
	}


	public function testGetConfigFE()
	{
		$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
		$orderManager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $orderManager->filter()->add( [
			'order.channel' => 'web',
			'order.statuspayment' => $status
		] );

		$item = $orderManager->search( $search, ['order/base', 'order/base/address'] )
			->first( new \RuntimeException( sprintf( 'No order found with status "%1$s" and channel "%2$s"', $status, 'web' ) ) );

		$config = $this->object->getConfigFE( $item->getBaseItem() );

		$this->assertArrayHasKey( 'novalnetsepa.iban', $config );
	}


	public function testCheckConfigFE()
	{
		$config = array(
			'novalnetsepa.bic' => 'ABCDEFGHIJK',
			'novalnetsepa.iban' => 'DE00102030405060708090',
			'novalnetsepa.holder' => 'test user',
		);

		$result = $this->object->checkConfigFE( $config );

		$expected = array(
			'novalnetsepa.bic' => null,
			'novalnetsepa.iban' => null,
			'novalnetsepa.holder' => null,
		);

		$this->assertEquals( $expected, $result );
	}


	public function testSetConfigFE()
	{
		$params = array(
			'novalnetsepa.bic' => 'ABCDEFGHIJK',
			'novalnetsepa.iban' => 'DE00102030405060708090',
			'novalnetsepa.holder' => 'test user',
		);

		$this->object->setConfigFE( $this->ordServItem, $params );

		$attrItem = $this->ordServItem->getAttributeItem( 'novalnetsepa.iban', 'session' );
		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Base\\Service\\Attribute\\Iface', $attrItem );
		$this->assertEquals( 'DE00102030405060708090', $attrItem->getValue() );
	}


	public function testProcess()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->setMethods( array( 'send' ) )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->setMethods( array( 'getTransactionReference', 'isSuccessful' ) )
			->disableOriginalConstructor()
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'purchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will($this->returnValue(''));


		$result = $this->object->process( $this->getOrder() );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( 'order.datepayment', '==', '2008-02-15 12:34:56' );

		return $manager->search( $search, ['order/base', 'order/base/product', 'order/base/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}
}
