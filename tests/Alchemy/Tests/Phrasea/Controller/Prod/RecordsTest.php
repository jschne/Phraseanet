<?php

namespace Alchemy\Tests\Phrasea\Controller\Prod;

use Alchemy\Phrasea\Border\File;
use Alchemy\Phrasea\SearchEngine\SearchEngineOptions;

/**
 * @todo Test Alchemy\Phrasea\Controller\Prod\Export::exportMail
 */
class RecordsTest extends \PhraseanetWebTestCaseAuthenticatedAbstract
{
    protected $client;
    protected static $feed;

    public static function tearDownAfterClass()
    {
        if (self::$feed instanceof \Feed_Adapter) {
            self::$feed->delete();
        }

        parent::tearDownAfterClass();
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::whatCanIDelete
     */
    public function testWhatCanIDelete()
    {
        self::$DI['client']->request('POST', '/prod/records/delete/what/', array('lst'     => self::$DI['record_1']->get_serialize_key()));
        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isOk());
        unset($response);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::doDeleteRecords
     */
    public function testDoDeleteRecords()
    {
        $file = new File(self::$DI['app'], self::$DI['app']['mediavorus']->guess(__DIR__ . '/../../../../../files/cestlafete.jpg'), self::$DI['collection']);
        $record = \record_adapter::createFromFile($file, self::$DI['app']);
        $this->XMLHTTPRequest('POST', '/prod/records/delete/', array('lst'     => $record->get_serialize_key()));
        $response = self::$DI['client']->getResponse();
        $datas = (array) json_decode($response->getContent());
        $this->assertContains($record->get_serialize_key(), $datas);
        try {
            new \record_adapter(self::$DI['app'], $record->get_sbas_id(), $record->get_record_id());
            $this->fail('Record not deleted');
        } catch (\Exception $e) {

        }
        unset($response, $datas, $record);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::renewUrl
     */
    public function testRenewUrl()
    {
        $file = new File(self::$DI['app'], self::$DI['app']['mediavorus']->guess(__DIR__ . '/../../../../../files/cestlafete.jpg'), self::$DI['collection']);
        $record = \record_adapter::createFromFile($file, self::$DI['app']);
        $this->XMLHTTPRequest('POST', '/prod/records/renew-url/', array('lst'     => $record->get_serialize_key()));
        $response = self::$DI['client']->getResponse();
        $datas = (array) json_decode($response->getContent());
        $this->assertTrue(count($datas) > 0);
        $record->delete();
        unset($response, $datas, $record);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailNotAjax()
    {
        self::$DI['client']->request('POST', '/prod/records/');

        $this->assertBadResponse(self::$DI['client']->getResponse());
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailResult()
    {
        self::$DI['app']['authentication']->openAccount(self::$DI['user']);
        self::$DI['record_24'];

        $options = new SearchEngineOptions();
        $acl = self::$DI['app']['authentication']->getUser()->ACL();
        $options->onCollections($acl->get_granted_base());
        $serializedOptions = $options->serialize();

        $this->XMLHTTPRequest('POST', '/prod/records/', array(
            'env'            => 'RESULT',
            'options_serial' => $serializedOptions,
            'pos'            => 0,
            'query'          => ''
        ));

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('desc', $data);
        $this->assertArrayHasKey('html_preview', $data);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('others', $data);
        $this->assertArrayHasKey('history', $data);
        $this->assertArrayHasKey('popularity', $data);
        $this->assertArrayHasKey('tools', $data);
        $this->assertArrayHasKey('pos', $data);
        $this->assertArrayHasKey('title', $data);

        unset($response, $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailREG()
    {
        self::$DI['app']['authentication']->openAccount(self::$DI['user']);
        self::$DI['record_story_1'];

        $this->XMLHTTPRequest('POST', '/prod/records/', array(
            'env'   => 'REG',
            'pos'   => 0,
            'query' => '',
            'cont'  =>   self::$DI['record_story_1']->get_serialize_key()
        ));

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent());
        $this->assertObjectHasAttribute('desc', $data);
        $this->assertObjectHasAttribute('html_preview', $data);
        $this->assertObjectHasAttribute('current', $data);
        $this->assertObjectHasAttribute('others', $data);
        $this->assertObjectHasAttribute('history', $data);
        $this->assertObjectHasAttribute('popularity', $data);
        $this->assertObjectHasAttribute('tools', $data);
        $this->assertObjectHasAttribute('pos', $data);
        $this->assertObjectHasAttribute('title', $data);

        unset($response, $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailBasket()
    {
        self::$DI['app']['authentication']->openAccount(self::$DI['user']);
        $basket = $this->insertOneBasket();
        $record = self::$DI['record_1'];

        $basketElement = new \Entities\BasketElement();
        $basketElement->setBasket($basket);
        $basketElement->setRecord($record);
        $basketElement->setLastInBasket();

        $basket->addElement($basketElement);

        self::$DI['app']['EM']->persist($basket);
        self::$DI['app']['EM']->flush();

        $this->XMLHTTPRequest('POST', '/prod/records/', array(
            'env'   => 'BASK',
            'pos'   => 0,
            'query' => '',
            'cont'  => $basket->getId()
        ));

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent());

        $this->assertObjectHasAttribute('desc', $data);
        $this->assertObjectHasAttribute('html_preview', $data);
        $this->assertObjectHasAttribute('current', $data);
        $this->assertObjectHasAttribute('others', $data);
        $this->assertObjectHasAttribute('history', $data);
        $this->assertObjectHasAttribute('popularity', $data);
        $this->assertObjectHasAttribute('tools', $data);
        $this->assertObjectHasAttribute('pos', $data);
        $this->assertObjectHasAttribute('title', $data);

        unset($response, $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailFeed()
    {
        self::$DI['app']['authentication']->openAccount(self::$DI['user']);

        self::$feed = \Feed_Adapter::create(
            self::$DI['app'],
            self::$DI['user'],
            'titi',
            'toto'
        );

        self::$DI['app']['notification.deliverer'] = $this->getMockBuilder('Alchemy\Phrasea\Notification\Deliverer')
            ->disableOriginalConstructor()
            ->getMock();

        self::$DI['app']['notification.deliverer']->expects($this->atLeastOnce())
            ->method('deliver')
            ->with($this->isInstanceOf('Alchemy\Phrasea\Notification\Mail\MailInfoNewPublication'), $this->equalTo(null));

        $feedEntry = \Feed_Entry_Adapter::create(
            self::$DI['app'],
            self::$feed,
            \Feed_Publisher_Adapter::getPublisher(
                self::$DI['app']['phraseanet.appbox'],
                self::$feed,
                self::$DI['user']
            ),
            'titi',
            'toto',
            'tata',
            'tutu@test.fr'
        );

        \Feed_Entry_Item::create(
            self::$DI['app']['phraseanet.appbox'],
            $feedEntry,
            self::$DI['record_1']
        );

        $this->XMLHTTPRequest('POST', '/prod/records/', array(
            'env'   => 'FEED',
            'pos'   => 0,
            'query' => '',
            'cont'  => $feedEntry->get_id()
        ));

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent());
        $this->assertObjectHasAttribute('desc', $data);
        $this->assertObjectHasAttribute('html_preview', $data);
        $this->assertObjectHasAttribute('current', $data);
        $this->assertObjectHasAttribute('others', $data);
        $this->assertObjectHasAttribute('history', $data);
        $this->assertObjectHasAttribute('popularity', $data);
        $this->assertObjectHasAttribute('tools', $data);
        $this->assertObjectHasAttribute('pos', $data);
        $this->assertObjectHasAttribute('title', $data);

        unset($response, $data);
    }
}
