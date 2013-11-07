<?php

namespace yiiunit\extensions\swiftmailer;

use Yii;
use yii\helpers\FileHelper;
use yii\swiftmailer\Mailer;
use yii\swiftmailer\Message;
use yiiunit\VendorTestCase;

/**
 * @group vendor
 * @group mail
 * @group swiftmailer
 */
class MessageTest extends VendorTestCase
{
	/**
	 * @var string test email address, which will be used as receiver for the messages.
	 */
	protected $testEmailReceiver = 'someuser@somedomain.com';

	public function setUp()
	{
		$this->mockApplication([
			'components' => [
				'mail' => $this->createTestEmailComponent()
			]
		]);
		$filePath = $this->getTestFilePath();
		if (!file_exists($filePath)) {
			FileHelper::createDirectory($filePath);
		}
	}

	public function tearDown()
	{
		$filePath = $this->getTestFilePath();
		if (file_exists($filePath)) {
			FileHelper::removeDirectory($filePath);
		}
	}

	/**
	 * @return string test file path.
	 */
	protected function getTestFilePath()
	{
		return Yii::getAlias('@yiiunit/runtime') . DIRECTORY_SEPARATOR . basename(get_class($this)) . '_' . getmypid();
	}

	/**
	 * @return Mailer test email component instance.
	 */
	protected function createTestEmailComponent()
	{
		$component = new Mailer();
		return $component;
	}

	/**
	 * @return Message test message instance.
	 */
	protected function createTestMessage()
	{
		return Yii::$app->getComponent('mail')->compose();
	}

	/**
	 * Creates image file with given text.
	 * @param string $fileName file name.
	 * @param string $text text to be applied on image.
	 * @return string image file full name.
	 */
	protected function createImageFile($fileName = 'test.jpg', $text = 'Test Image')
	{
		if (!function_exists('imagecreatetruecolor')) {
			$this->markTestSkipped('GD lib required.');
		}
		$fileFullName = $this->getTestFilePath() . DIRECTORY_SEPARATOR . $fileName;
		$image = imagecreatetruecolor(120, 20);
		$textColor = imagecolorallocate($image, 233, 14, 91);
		imagestring($image, 1, 5, 5, $text, $textColor);
		imagejpeg($image, $fileFullName);
		imagedestroy($image);
		return $fileFullName;
	}

	/**
	 * Finds the attachment object in the message.
	 * @param Message $message message instance
	 * @return null|\Swift_Mime_Attachment attachment instance.
	 */
	protected function getAttachment(Message $message)
	{
		$messageParts = $message->getSwiftMessage()->getChildren();
		$attachment = null;
		foreach ($messageParts as $part) {
			if ($part instanceof \Swift_Mime_Attachment) {
				$attachment = $part;
				break;
			}
		}
		return $attachment;
	}

	// Tests :

	public function testGetSwiftMessage()
	{
		$message = new Message();
		$this->assertTrue(is_object($message->getSwiftMessage()), 'Unable to get Swift message!');
	}

	/**
	 * @depends testGetSwiftMessage
	 */
	public function testSetupHeaders()
	{
		$charset = 'utf-16';
		$subject = 'Test Subject';
		$to = 'someuser@somedomain.com';
		$cc = 'ccuser@somedomain.com';
		$bcc = 'bccuser@somedomain.com';

		$messageString = $this->createTestMessage()
			->charset($charset)
			->subject($subject)
			->to($to)
			->cc($cc)
			->bcc($bcc)
			->toString();

		$this->assertContains('charset=' . $charset, $messageString, 'Incorrect charset!');
		$this->assertContains('Subject: ' . $subject, $messageString, 'Incorrect "Subject" header!');
		$this->assertContains('To: ' . $to, $messageString, 'Incorrect "To" header!');
		$this->assertContains('Cc: ' . $cc, $messageString, 'Incorrect "Cc" header!');
		$this->assertContains('Bcc: ' . $bcc, $messageString, 'Incorrect "Bcc" header!');
	}

	/**
	 * @depends testGetSwiftMessage
	 */
	public function testSetupFrom()
	{
		$from = 'someuser@somedomain.com';
		$messageString = $this->createTestMessage()
			->from($from)
			->toString();
		$this->assertContains('From: ' . $from, $messageString, 'Incorrect "From" header!');
		$this->assertContains('Reply-To: ' . $from, $messageString, 'Incorrect "Reply-To" header!');
	}

	/**
	 * @depends testGetSwiftMessage
	 */
	public function testSend()
	{
		$message = $this->createTestMessage();
		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Test');
		$message->textBody('Yii Swift Test body');
		$this->assertTrue($message->send());
	}

	/**
	 * @depends testSend
	 */
	public function testAttachFile()
	{
		$message = $this->createTestMessage();

		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Attach File Test');
		$message->textBody('Yii Swift Attach File Test body');
		$fileName = __FILE__;
		$message->attach($fileName);

		$this->assertTrue($message->send());

		$attachment = $this->getAttachment($message);
		$this->assertTrue(is_object($attachment), 'No attachment found!');
		$this->assertContains($attachment->getFilename(), $fileName, 'Invalid file name!');
	}

	/**
	 * @depends testSend
	 */
	public function testAttachContent()
	{
		$message = $this->createTestMessage();

		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Create Attachment Test');
		$message->textBody('Yii Swift Create Attachment Test body');
		$fileName = 'test.txt';
		$fileContent = 'Test attachment content';
		$message->attachContent($fileContent, ['fileName' => $fileName]);

		$this->assertTrue($message->send());

		$attachment = $this->getAttachment($message);
		$this->assertTrue(is_object($attachment), 'No attachment found!');
		$this->assertEquals($fileName, $attachment->getFilename(), 'Invalid file name!');
	}

	/**
	 * @depends testSend
	 */
	public function testEmbedFile()
	{
		$fileName = $this->createImageFile('embed_file.jpg', 'Embed Image File');

		$message = $this->createTestMessage();

		$cid = $message->embed($fileName);

		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Embed File Test');
		$message->htmlBody('Embed image: <img src="' . $cid. '" alt="pic">');

		$this->assertTrue($message->send());

		$attachment = $this->getAttachment($message);
		$this->assertTrue(is_object($attachment), 'No attachment found!');
		$this->assertContains($attachment->getFilename(), $fileName, 'Invalid file name!');
	}

	/**
	 * @depends testSend
	 */
	public function testEmbedContent()
	{
		$fileFullName = $this->createImageFile('embed_file.jpg', 'Embed Image File');
		$message = $this->createTestMessage();

		$fileName = basename($fileFullName);
		$contentType = 'image/jpeg';
		$fileContent = file_get_contents($fileFullName);

		$cid = $message->embedContent($fileContent, ['fileName' => $fileName, 'contentType' => $contentType]);

		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Embed File Test');
		$message->htmlBody('Embed image: <img src="' . $cid. '" alt="pic">');

		$this->assertTrue($message->send());

		$attachment = $this->getAttachment($message);
		$this->assertTrue(is_object($attachment), 'No attachment found!');
		$this->assertEquals($fileName, $attachment->getFilename(), 'Invalid file name!');
		$this->assertEquals($contentType, $attachment->getContentType(), 'Invalid content type!');
	}

	/**
	 * @depends testSend
	 */
	public function testSendAlternativeBody()
	{
		$message = $this->createTestMessage();

		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Alternative Body Test');
		$message->htmlBody('<b>Yii Swift</b> test HTML body');
		$message->textBody('Yii Swift test plain text body');

		$this->assertTrue($message->send());

		$messageParts = $message->getSwiftMessage()->getChildren();
		$textPresent = false;
		$htmlPresent = false;
		foreach ($messageParts as $part) {
			if (!($part instanceof \Swift_Mime_Attachment)) {
				/* @var $part \Swift_Mime_MimePart */
				if ($part->getContentType() == 'text/plain') {
					$textPresent = true;
				}
				if ($part->getContentType() == 'text/html') {
					$htmlPresent = true;
				}
			}
		}
		$this->assertTrue($textPresent, 'No text!');
		$this->assertTrue($htmlPresent, 'No HTML!');
	}

	/**
	 * @depends testGetSwiftMessage
	 */
	public function testSerialize()
	{
		$message = $this->createTestMessage();

		$message->to($this->testEmailReceiver);
		$message->from('someuser@somedomain.com');
		$message->subject('Yii Swift Alternative Body Test');
		$message->textBody('Yii Swift test plain text body');

		$serializedMessage = serialize($message);
		$this->assertNotEmpty($serializedMessage, 'Unable to serialize message!');

		$unserializedMessaage = unserialize($serializedMessage);
		$this->assertEquals($message, $unserializedMessaage, 'Unable to unserialize message!');
	}
}