<?php

namespace Tests\Unit\Services\GenieACS;

use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Repositories\GenieACSRepository;
use Mockery;
use Tests\TestCase;

class GenieacsRepositoryTest extends TestCase
{
    protected GenieACSRepository $repository;

    protected IGenieACSClient $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(IGenieACSClient::class);
        $this->repository = new GenieACSRepository($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_devices_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('devices')
            ->once()
            ->with([], [], 50, 0)
            ->andReturn([['_id' => 'DEV-001']]);

        $result = $this->repository->getDevices();

        $this->assertCount(1, $result);
    }

    public function test_get_devices_with_filters_builds_query(): void
    {
        $this->mockClient
            ->shouldReceive('devices')
            ->once()
            ->with(
                Mockery::on(fn ($query) => isset($query['InternetGatewayDevice.DeviceInfo.ModelName'])),
                [],
                10,
                0
            )
            ->andReturn([['_id' => 'DEV-001']]);

        $result = $this->repository->getDevices(['model' => 'HG8245H'], 10);

        $this->assertCount(1, $result);
    }

    public function test_get_device_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('device')
            ->once()
            ->with('DEV-001')
            ->andReturn(['_id' => 'DEV-001']);

        $result = $this->repository->getDevice('DEV-001');

        $this->assertEquals('DEV-001', $result['_id']);
    }

    public function test_count_devices(): void
    {
        $this->mockClient
            ->shouldReceive('devices')
            ->once()
            ->andReturn([['_id' => 'a'], ['_id' => 'b']]);

        $count = $this->repository->countDevices();

        $this->assertEquals(2, $count);
    }

    public function test_get_faults_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('faults')
            ->once()
            ->with([], 25, 0)
            ->andReturn([['_id' => 'F-1']]);

        $result = $this->repository->getFaults([], 25);

        $this->assertCount(1, $result);
    }

    public function test_get_faults_by_device(): void
    {
        $this->mockClient
            ->shouldReceive('faults')
            ->once()
            ->with(['device' => 'DEV-001'])
            ->andReturn([['_id' => 'F-1']]);

        $result = $this->repository->getFaultsByDevice('DEV-001');

        $this->assertCount(1, $result);
    }

    public function test_get_presets_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('presets')
            ->once()
            ->andReturn(['inform' => ['weight' => 0]]);

        $result = $this->repository->getPresets();

        $this->assertArrayHasKey('inform', $result);
    }

    public function test_reboot_device_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('reboot')
            ->once()
            ->with('DEV-001')
            ->andReturn(['name' => 'reboot']);

        $result = $this->repository->rebootDevice('DEV-001');

        $this->assertEquals('reboot', $result['name']);
    }

    public function test_factory_reset_device_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('factoryReset')
            ->once()
            ->with('DEV-001')
            ->andReturn(['name' => 'factoryReset']);

        $result = $this->repository->factoryResetDevice('DEV-001');

        $this->assertEquals('factoryReset', $result['name']);
    }

    public function test_set_parameter_values_delegates_to_client(): void
    {
        $params = [['InternetGatewayDevice.DeviceInfo.Hostname', 'New', 'xsd:string']];

        $this->mockClient
            ->shouldReceive('setParameterValues')
            ->once()
            ->with('DEV-001', $params)
            ->andReturn(['name' => 'setParameterValues']);

        $result = $this->repository->setParameterValues('DEV-001', $params);

        $this->assertEquals('setParameterValues', $result['name']);
    }

    public function test_get_parameter_values_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('getParameterValues')
            ->once()
            ->with('DEV-001', ['InternetGatewayDevice.DeviceInfo.ModelName'])
            ->andReturn(['name' => 'getParameterValues']);

        $result = $this->repository->getParameterValues('DEV-001', ['InternetGatewayDevice.DeviceInfo.ModelName']);

        $this->assertEquals('getParameterValues', $result['name']);
    }

    public function test_download_firmware_delegates_to_client(): void
    {
        $this->mockClient
            ->shouldReceive('downloadFirmware')
            ->once()
            ->with('DEV-001', 'firmware_v1.bin')
            ->andReturn(['name' => 'download']);

        $result = $this->repository->downloadFirmware('DEV-001', 'firmware_v1.bin');

        $this->assertEquals('download', $result['name']);
    }
}
