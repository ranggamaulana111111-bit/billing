<?php

namespace Tests\Unit\Services\GenieACS;

use App\Modules\GenieACS\DTO\DeviceDTO;
use App\Modules\GenieACS\DTO\FaultDTO;
use App\Modules\GenieACS\DTO\PresetDTO;
use App\Modules\GenieACS\DTO\TaskDTO;
use Tests\TestCase;

class GenieacsDTOTest extends TestCase
{
    public function test_device_dto_from_response(): void
    {
        $data = [
            '_id' => 'ABC123-HW-0001',
            'InternetGatewayDevice.DeviceInfo.Manufacturer' => 'Huawei',
            'InternetGatewayDevice.DeviceInfo.ModelName' => 'HG8245H',
            'InternetGatewayDevice.DeviceInfo.SoftwareVersion' => 'V300R013C10S115',
            'InternetGatewayDevice.DeviceInfo.SerialNumber' => 'SN12345678',
            '_lastInform' => '2026-07-25 10:00:00',
            '_tags' => ['active', 'fiber'],
        ];

        $dto = DeviceDTO::fromResponse($data);

        $this->assertEquals('ABC123-HW-0001', $dto->id);
        $this->assertEquals('Huawei', $dto->manufacturer);
        $this->assertEquals('HG8245H', $dto->modelName);
        $this->assertEquals('V300R013C10S115', $dto->softwareVersion);
        $this->assertEquals('SN12345678', $dto->serialNumber);
        $this->assertEquals('2026-07-25 10:00:00', $dto->lastInform);
        $this->assertEquals(['active', 'fiber'], $dto->tags);
        $this->assertArrayHasKey('InternetGatewayDevice.DeviceInfo.Manufacturer', $dto->parameters);
    }

    public function test_device_dto_get_display_name(): void
    {
        $dto = DeviceDTO::fromResponse(['_id' => 'DEV-001', 'InternetGatewayDevice.DeviceInfo.ModelName' => 'HG8245H']);
        $this->assertEquals('HG8245H', $dto->getDisplayName());

        $dto = DeviceDTO::fromResponse(['_id' => 'DEV-001']);
        $this->assertEquals('DEV-001', $dto->getDisplayName());
    }

    public function test_device_dto_to_array(): void
    {
        $dto = DeviceDTO::fromResponse(['_id' => 'DEV-001']);
        $arr = $dto->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('model_name', $arr);
        $this->assertArrayHasKey('tags', $arr);
    }

    public function test_device_dto_from_collection(): void
    {
        $devices = [
            ['_id' => 'DEV-001'],
            ['_id' => 'DEV-002'],
        ];

        $dtos = DeviceDTO::fromCollection($devices);

        $this->assertCount(2, $dtos);
        $this->assertInstanceOf(DeviceDTO::class, $dtos[0]);
    }

    public function test_task_dto_from_response(): void
    {
        $data = [
            '_id' => 'task-abc',
            'device' => 'DEV-001',
            'name' => 'reboot',
            'status' => 'completed',
            'retries' => 0,
            'timestamp' => '2026-07-25 10:00:00',
        ];

        $dto = TaskDTO::fromResponse($data);

        $this->assertEquals('task-abc', $dto->id);
        $this->assertEquals('DEV-001', $dto->deviceId);
        $this->assertEquals('reboot', $dto->name);
        $this->assertTrue($dto->isCompleted());
        $this->assertFalse($dto->isFaulty());
    }

    public function test_task_dto_is_faulty(): void
    {
        $dto = TaskDTO::fromResponse(['_id' => 't1', 'device' => 'd1', 'name' => 'reboot', 'status' => 'fault']);

        $this->assertTrue($dto->isFaulty());
        $this->assertFalse($dto->isCompleted());
    }

    public function test_fault_dto_from_response(): void
    {
        $data = [
            '_id' => 'DEV-001:default',
            'device' => 'DEV-001',
            'channel' => 'default',
            'code' => 1,
            'message' => 'Failure in contacting device',
            'retries' => 3,
            'timestamp' => '2026-07-25 10:00:00',
        ];

        $dto = FaultDTO::fromResponse($data);

        $this->assertEquals('DEV-001:default', $dto->id);
        $this->assertEquals('DEV-001', $dto->deviceId);
        $this->assertEquals('default', $dto->channel);
        $this->assertEquals(1, $dto->code);
        $this->assertEquals(3, $dto->retries);
        $this->assertEquals('Failure in contacting device', $dto->getCodeLabel());
    }

    public function test_fault_dto_get_code_label_unknown(): void
    {
        $dto = FaultDTO::fromResponse(['_id' => 'f1', 'device' => 'd1', 'channel' => 'c', 'code' => 99]);

        $this->assertEquals('Fault-99', $dto->getCodeLabel());
    }

    public function test_preset_dto_from_response(): void
    {
        $data = [
            'weight' => 10,
            'precondition' => '{"_tags": "test"}',
            'configurations' => [
                ['type' => 'value', 'name' => 'PeriodicInformInterval', 'value' => '300'],
            ],
            'schedule' => '0 0 * * *',
        ];

        $dto = PresetDTO::fromResponse('inform', $data);

        $this->assertEquals('inform', $dto->name);
        $this->assertEquals(10, $dto->weight);
        $this->assertTrue($dto->hasPrecondition());
        $this->assertTrue($dto->hasSchedule());
        $this->assertEquals(1, $dto->getConfigurationCount());
    }

    public function test_preset_dto_from_collection(): void
    {
        $raw = [
            'presetA' => ['weight' => 0],
            'presetB' => ['weight' => 1],
        ];

        $dtos = PresetDTO::fromCollection($raw);

        $this->assertCount(2, $dtos);
        $this->assertEquals('presetA', $dtos[0]->name);
    }
}
