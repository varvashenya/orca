<?php

namespace Acquia\Orca\Tests\Command\Fixture;

use Acquia\Orca\Command\Fixture\FixtureStatusCommand;
use Acquia\Orca\Command\StatusCodes;
use Acquia\Orca\Fixture\Fixture;
use Acquia\Orca\Fixture\FixtureInspector;
use Acquia\Orca\Tests\Command\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\Fixture $fixture
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\FixtureInspector $fixtureInspector
 */
class FixtureStatusCommandTest extends CommandTestBase {

  protected function setUp() {
    $this->fixture = $this->prophesize(Fixture::class);
    $this->fixture->exists()
      ->willReturn(TRUE);
    $this->fixture->getPath()
      ->willReturn(self::FIXTURE_ROOT);
    $this->fixtureInspector = $this->prophesize(FixtureInspector::class);
  }

  protected function createCommand(): Command {
    /** @var \Acquia\Orca\Fixture\Fixture $fixture */
    $fixture = $this->fixture->reveal();
    /** @var \Acquia\Orca\Fixture\FixtureInspector $fixture_inspector */
    $fixture_inspector = $this->fixtureInspector->reveal();
    return new FixtureStatusCommand($fixture, $fixture_inspector);
  }

  /**
   * @dataProvider providerCommand
   */
  public function testCommand($fixture_exists, $get_overview_called, $status_code, $display) {
    $this->fixture
      ->exists()
      ->shouldBeCalled()
      ->willReturn($fixture_exists);
    $this->fixtureInspector
      ->getOverview()
      ->shouldBeCalledTimes($get_overview_called)
      ->willReturn([
        ['Key one', 'Value one'],
        ['Key two', 'Value two'],
      ]);

    $this->executeCommand();

    $this->assertEquals($display, $this->getDisplay(), 'Displayed correct output.');
    $this->assertEquals($status_code, $this->getStatusCode(), 'Returned correct status code.');
  }

  public function providerCommand() {
    return [
      [FALSE, 0, StatusCodes::ERROR, sprintf("Error: No fixture exists at %s.\n", self::FIXTURE_ROOT)],
      [TRUE, 1, StatusCodes::OK, "\n Key one : Value one \n Key two : Value two \n\n"],
    ];
  }

}
