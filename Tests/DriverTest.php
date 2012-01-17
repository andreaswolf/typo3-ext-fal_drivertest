<?php

/**
 * A generic driver testcase for the TYPO3 File Abstraction Layer.
 *
 * This testcase is meant to be generic enough to test every possible driver. It is thus no unit test in the most narrow
 * definition, but more a functional test. The test also solely relies on the methods the driver supplies, so it will
 * not work if the driver is buggy.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class Tx_FalDrivertest_DriverTest extends Tx_Phpunit_TestCase {

	/**
	 * @var int
	 */
	private static $storage;

	/**
	 * @var t3lib_file_Storage
	 */
	private $fixture;

	/**
	 * @var string
	 */
	private $testFolderName;

	/**
	 * @var bool
	 */
	private $testFolderExists = FALSE;

	public static function setUpBeforeClass() {
		if (isset($_SERVER['FAL_STORAGE'])) {
			self::$storage = intval($_SERVER['FAL_STORAGE']);
		}

		if (!self::$storage) {
			self::fail('No storage defined to test against. Define it with setting the environment variable FAL_STORAGE');
		}
	}

	public function setUp() {
		/** @var $factory t3lib_file_Factory */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');

		$this->fixture = $factory->getStorageObject(self::$storage);

		$this->testFolderName = uniqid();
	}

	public function tearDown() {
		if ($this->testFolderExists) {
			$this->deleteTestFolder();
		}
	}

	protected function getTestFolderObject() {
		return $this->fixture->getFolder('/' . $this->testFolderName . '/');
	}

	protected function createTestFolder() {
		$this->fixture->createFolder($this->testFolderName, $this->fixture->getRootLevelFolder());
		$this->testFolderExists = TRUE;
	}

	protected function deleteTestFolder() {
		$this->fixture->deleteFolder($this->getTestFolderObject(), TRUE);
		$this->testFolderExists = FALSE;
	}


	/**
	 * Note: This method will not work if checking folder existence does not work.
	 *
	 * @test
	 */
	public function foldersCanBeCreatedAndDeleted() {
		$this->createTestFolder();
		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/'), 'Creating the test folder did not work.');

		$this->deleteTestFolder();
		$this->assertFalse($this->fixture->hasFolder('/' . $this->testFolderName . '/'), 'Deleting the test folder did not work.');
	}

	/**
	 * @test
	 */
	public function filesCanBeCreatedAndDeleted() {
		$fileIdentifier = '/' . $this->testFolderName . '/testFile';

		$this->createTestFolder();
		$this->fixture->createFile('testFile', $this->getTestFolderObject());
		$this->assertTrue($this->fixture->hasFile($fileIdentifier));
		$this->assertTrue($this->fixture->hasFileInFolder('testFile', $this->getTestFolderObject()));

		$this->fixture->deleteFile($this->fixture->getFile($fileIdentifier));
		$this->assertFalse($this->fixture->hasFile($fileIdentifier));
		$this->assertFalse($this->fixture->hasFileInFolder('testFile', $this->getTestFolderObject()));
	}

	/**
	 * @test
	 */
	public function filesInFolderCanBeListed() {
		$this->createTestFolder();
		$filenames = array(uniqid(), uniqid());

		$this->fixture->createFile($filenames[0], $this->getTestFolderObject());
		$this->fixture->createFile($filenames[1], $this->getTestFolderObject());

		$folderContents = $this->fixture->getFileList('/' . $this->testFolderName . '/');
			// manually sort results as the driver does not care about this - this is usually done by the storage
		$this->assertEquals(sort($filenames), sort(array_keys($folderContents)));
	}

	/**
	 * @test
	 */
	public function foldersCanBeListed() {
		$this->markTestIncomplete();
	}

	/**
	 * @test
	 */
	public function fileContentsCanBeSetAndRetrieved() {
		$fileContents = uniqid();

		$this->createTestFolder();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());
		$this->fixture->setFileContents($fileObject, $fileContents);

		$this->assertEquals($fileContents, $this->fixture->getFileContents($fileObject));

		$this->fixture->deleteFile($fileObject);
	}

	/**
	 * @test
	 */
	public function fileMetadataIsCorrectlyRetrieved() {
		$fileContents = uniqid();

		$this->createTestFolder();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());
		$this->fixture->setFileContents($fileObject, $fileContents);

		$this->assertEquals(strlen($fileContents), $fileObject->getSize());
		//$this->assertEquals(time(), $fileObject->getProperty('ctime'));
		// TODO add more metadata
		$this->markTestIncomplete();
	}

	/**
	 * @test
	 */
	public function filesCanBeHashedWithSha1() {
		$fileContents = uniqid();
		$hash = sha1($fileContents);

		$this->createTestFolder();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());
		$this->fixture->setFileContents($fileObject, $fileContents);

		$this->assertEquals($hash, $this->fixture->hashFile($fileObject, 'sha1'));

		$this->fixture->deleteFile($fileObject);
	}

	/**
	 * @test
	 */
	public function filesCanBeAdded() {
		$tempFile = t3lib_div::tempnam('fal-drivertest-');
		$fileContents = uniqid();
		file_put_contents($tempFile, $fileContents);

		$this->createTestFolder();
		$fileObject = $this->fixture->addFile($tempFile, $this->getTestFolderObject(), 'testFile');

		$this->assertEquals($fileContents, $this->fixture->getFileContents($fileObject));

		unlink($tempFile);
	}

	/**
	 * @test
	 */
	public function filesCanBeReplaced() {
		$tempFile = t3lib_div::tempnam('fal-drivertest-');
		$fileContents = uniqid();
		file_put_contents($tempFile, $fileContents);

		$this->createTestFolder();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());
		$this->fixture->replaceFile($fileObject, $tempFile);

		$this->assertEquals($fileContents, $this->fixture->getFileContents($fileObject));

		unlink($tempFile);
	}

	/**
	 * @test
	 */
	public function filesCanBeRenamed() {
		$this->createTestFolder();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());
		$this->assertTrue($this->fixture->hasFile('/' . $this->testFolderName . '/testFile'));

		$this->fixture->renameFile($fileObject, 'newFile');
		$this->assertFalse($this->fixture->hasFile('/' . $this->testFolderName . '/testFile'));
		$this->assertTrue($this->fixture->hasFile('/' . $this->testFolderName . '/newFile'));
	}

	/**
	 * @test
	 */
	public function filesCanBeMovedBetweenFolders() {
		$this->createTestFolder();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());
		$subfolder = $this->fixture->createFolder('someFolder', $this->getTestFolderObject());

		$this->fixture->moveFile($fileObject, $subfolder);
	}

	/**
	 * @test
	 */
	public function filesCanBeCopied() {
		$this->createTestFolder();
		$fileContents = uniqid();
		$fileObject = $this->fixture->createFile('testFile', $this->getTestFolderObject());

		$this->fixture->setFileContents($fileObject, $fileContents);
		$newFile = $this->fixture->copyFile($fileObject, $this->getTestFolderObject(), 'copiedFile');

		$this->assertTrue($this->fixture->hasFileInFolder('testFile', $this->getTestFolderObject()));
		$this->assertTrue($this->fixture->hasFileInFolder('copiedFile', $this->getTestFolderObject()));

		$this->assertEquals($fileContents, $this->fixture->getFileContents($fileObject));
		$this->assertEquals($fileContents, $this->fixture->getFileContents($newFile));
	}

	/**
	 * @test
	 */
	public function foldersCanBeRenamed() {
		$this->createTestFolder();
		$fileObject = $this->fixture->createFolder('testFolder', $this->getTestFolderObject());
		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/testFolder/'));

		$this->fixture->renameFolder($fileObject, 'newFolder');
		$this->assertFalse($this->fixture->hasFolder('/' . $this->testFolderName . '/testFolder/'));
		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/newFolder/'));
	}

	/**
	 * @test
	 */
	public function foldersCanBeMovedInsideStorage() {
		$this->createTestFolder();
		$sourceFolder = $this->fixture->createFolder('someFolder', $this->getTestFolderObject());
		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/someFolder/'));
		$this->fixture->createFile('someFile', $sourceFolder);
		$targetFolder = $this->fixture->createFolder('someOtherFolder', $this->getTestFolderObject());

		$this->fixture->moveFolder($sourceFolder, $targetFolder);

		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/someOtherFolder/someFolder/'));
		$this->assertTrue($this->fixture->hasFile('/' . $this->testFolderName . '/someOtherFolder/someFolder/someFile'));
	}

	/**
	 * @test
	 */
	public function foldersCanBeCopiedInsideStorage() {
		$this->createTestFolder();
		$sourceFolder = $this->fixture->createFolder('someFolder', $this->getTestFolderObject());
		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/someFolder/'));
		$this->fixture->createFile('someFile', $sourceFolder);
		$targetFolder = $this->fixture->createFolder('someOtherFolder', $this->getTestFolderObject());

		$this->fixture->copyFolder($sourceFolder, $targetFolder, 'copiedFolder');

		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/someFolder/'));
		$this->assertTrue($this->fixture->hasFile('/' . $this->testFolderName . '/someFolder/someFile'));
		$this->assertTrue($this->fixture->hasFolder('/' . $this->testFolderName . '/someOtherFolder/copiedFolder/'));
		$this->assertTrue($this->fixture->hasFile('/' . $this->testFolderName . '/someOtherFolder/copiedFolder/someFile'));
	}
}
