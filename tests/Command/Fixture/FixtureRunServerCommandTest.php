<?php

namespace Acquia\Orca\Tests\Command\Fixture;

use Acquia\Orca\Command\Fixture\FixtureRunServerCommand;
use Acquia\Orca\Command\StatusCodes;
use Acquia\Orca\Fixture\Fixture;
use Acquia\Orca\Server\WebServer;
use Acquia\Orca\Tests\Command\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\Fixture $fixture
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Server\WebServer $webServer
 */
class FixtureRunServerCommandTest extends CommandTestBase {

  protected function setUp() {
    $this->fixture = $this->prophesize(Fixture::class);
    $this->fixture->getPath()
      ->willReturn(self::FIXTURE_ROOT);
    $this->fixture->getPath('docroot')
      ->willReturn(self::FIXTURE_DOCROOT);
    $this->webServer = $this->prophesize(WebServer::class);
  }

  protected function createCommand(): Command {
    /** @var \Acquia\Orca\Fixture\Fixture $fixture */
    $fixture = $this->fixture->reveal();
    /** @var \Acquia\Orca\Server\WebServer $web_server */
    $web_server = $this->webServer->reveal();
    return new FixtureRunServerCommand($fixture, $web_server);
  }

  /**
   * @dataProvider providerCommand
   */
  public function testCommand($fixture_exists, $methods_called, $status_code, $display) {
    $this->fixture
      ->exists()
      ->shouldBeCalledTimes((int) in_array('exists', $methods_called))
      ->willReturn($fixture_exists);
    $this->webServer
      ->start()
      ->shouldBeCalledTimes((int) in_array('start', $methods_called));
    $this->webServer
      ->wait()
      ->shouldBeCalledTimes((int) in_array('wait', $methods_called));

    $this->executeCommand();

    $this->assertEquals($display, $this->getDisplay(), 'Displayed correct output.');
    $this->assertEquals($status_code, $this->getStatusCode(), 'Returned correct status code.');
  }

  public function providerCommand() {
    return [
      [FALSE, ['exists'], StatusCodes::ERROR, sprintf("Error: No fixture exists at %s.\nHint: Use the \"fixture:init\" command to create one.\n", self::FIXTURE_ROOT)],
      [TRUE, ['exists', 'start', 'wait'], StatusCodes::OK, sprintf("Starting web server...\nListening on http://%s.\nDocument root is %s.\nPress Ctrl-C to quit.\n", Fixture::WEB_ADDRESS, self::FIXTURE_DOCROOT)],
    ];
  }

}
