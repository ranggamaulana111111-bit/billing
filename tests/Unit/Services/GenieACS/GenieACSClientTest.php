<?php

namespace Tests\Unit\Services\GenieACS;

use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Exceptions\GenieACSApiException;
use App\Modules\GenieACS\Exceptions\GenieACSAuthenticationException;
use App\Modules\GenieACS\Services\GenieACSClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenieACSClientTest extends TestCase
{
    protected GenieACSClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('settings', function ($table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        config([
            'genieacs.base_url' => 'http://localhost:7557',
            'genieacs.username' => 'admin',
            'genieacs.password' => 'secret',
            'genieacs.timeout' => 30,
        ]);

        $this->client = new GenieACSClient;
    }

    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(IGenieACSClient::class, $this->client);
    }

    // ── testConnection ─────────────────────────────────────

    public function test_test_connection_returns_success(): void
    {
        Http::fake([
            'http://localhost:7557/devices*' => Http::response([
                ['_id' => 'DEVICE-001'],
            ], 200),
        ]);

        $result = $this->client->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('Connected to GenieACS', $result['message']);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertEquals(1, $result['device_count']);
    }

    public function test_test_connection_returns_error_on_failure(): void
    {
        Http::fake([
            'http://localhost:7557/*' => Http::response(null, 500),
        ]);

        $result = $this->client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('response_time', $result);
    }

    public function test_test_connection_handles_connection_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $result = $this->client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('response_time', $result);
    }

    // ── devices ────────────────────────────────────────────

    public function test_devices_returns_array(): void
    {
        Http::fake([
            'http://localhost:7557/devices*' => Http::response([
                ['_id' => 'ABC123-ONU-001', 'modelName' => 'HG8245H'],
                ['_id' => 'DEF456-ONU-002', 'modelName' => 'F670L'],
            ], 200),
        ]);

        $result = $this->client->devices();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('ABC123-ONU-001', $result[0]['_id']);
    }

    public function test_devices_with_query(): void
    {
        Http::fake([
            'http://localhost:7557/devices*' => Http::response([
                ['_id' => 'ABC123-ONU-001'],
            ], 200),
        ]);

        $result = $this->client->devices(['_id' => 'ABC123-ONU-001']);

        $this->assertCount(1, $result);
    }

    // ── device ─────────────────────────────────────────────

    public function test_device_returns_single(): void
    {
        Http::fake([
            'http://localhost:7557/devices*' => Http::response([
                ['_id' => 'ABC123-ONU-001', 'modelName' => 'HG8245H'],
            ], 200),
        ]);

        $result = $this->client->device('ABC123-ONU-001');

        $this->assertIsArray($result);
        $this->assertEquals('ABC123-ONU-001', $result['_id']);
    }

    public function test_device_returns_null_when_not_found(): void
    {
        Http::fake([
            'http://localhost:7557/devices*' => Http::response([], 200),
        ]);

        $result = $this->client->device('NONEXISTENT');

        $this->assertNull($result);
    }

    // ── tasks ──────────────────────────────────────────────

    public function test_tasks_returns_array(): void
    {
        Http::fake([
            'http://localhost:7557/tasks*' => Http::response([
                ['_id' => 'task123', 'device' => 'DEV-001', 'name' => 'getParameterValues', 'status' => 'completed'],
            ], 200),
        ]);

        $result = $this->client->tasks('DEV-001');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('completed', $result[0]['status']);
    }

    // ── faults ─────────────────────────────────────────────

    public function test_faults_returns_array(): void
    {
        Http::fake([
            'http://localhost:7557/faults*' => Http::response([
                ['_id' => 'DEV-001:default', 'device' => 'DEV-001', 'code' => 0],
            ], 200),
        ]);

        $result = $this->client->faults();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ── presets ────────────────────────────────────────────

    public function test_presets_returns_array(): void
    {
        Http::fake([
            'http://localhost:7557/presets*' => Http::response([
                'inform' => ['weight' => 0],
            ], 200),
        ]);

        $result = $this->client->presets();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('inform', $result);
    }

    // ── provisions ─────────────────────────────────────────

    public function test_provisions_returns_array(): void
    {
        Http::fake([
            'http://localhost:7557/provisions*' => Http::response([
                'myScript' => 'log("hello");',
            ], 200),
        ]);

        $result = $this->client->provisions();

        $this->assertIsArray($result);
    }

    // ── reboot ─────────────────────────────────────────────

    public function test_reboot_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'reboot', 'status' => 'pending'], 200),
        ]);

        $result = $this->client->reboot('DEV-001');

        $this->assertEquals('reboot', $result['name']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/devices/DEV-001/tasks');
        });
    }

    // ── factoryReset ───────────────────────────────────────

    public function test_factory_reset_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'factoryReset', 'status' => 'pending'], 200),
        ]);

        $result = $this->client->factoryReset('DEV-001');

        $this->assertEquals('factoryReset', $result['name']);
    }

    // ── downloadFirmware ───────────────────────────────────

    public function test_download_firmware_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'download', 'file' => 'fw.bin', 'status' => 'pending'], 200),
        ]);

        $result = $this->client->downloadFirmware('DEV-001', 'fw.bin');

        $this->assertEquals('download', $result['name']);
        $this->assertEquals('fw.bin', $result['file']);
    }

    // ── refreshObject ──────────────────────────────────────

    public function test_refresh_object_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'refreshObject', 'status' => 'pending'], 200),
        ]);

        $result = $this->client->refreshObject('DEV-001', 'InternetGatewayDevice.');

        $this->assertEquals('refreshObject', $result['name']);
    }

    // ── setParameterValues ─────────────────────────────────

    public function test_set_parameter_values_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'setParameterValues', 'status' => 'pending'], 200),
        ]);

        $params = [['InternetGatewayDevice.DeviceInfo.Hostname', 'NewHost', 'xsd:string']];
        $result = $this->client->setParameterValues('DEV-001', $params);

        $this->assertEquals('setParameterValues', $result['name']);
    }

    // ── getParameterValues ─────────────────────────────────

    public function test_get_parameter_values_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'getParameterValues', 'status' => 'completed'], 200),
        ]);

        $result = $this->client->getParameterValues('DEV-001', ['InternetGatewayDevice.DeviceInfo.ModelName']);

        $this->assertEquals('getParameterValues', $result['name']);
    }

    // ── connectionRequest ──────────────────────────────────

    public function test_connection_request_sends_post(): void
    {
        Http::fake([
            'http://localhost:7557/devices/*/tasks*' => Http::response(['name' => 'connectionRequest'], 200),
        ]);

        $result = $this->client->connectionRequest('DEV-001');

        $this->assertIsArray($result);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'connection_request');
        });
    }

    // ── Auth header ────────────────────────────────────────

    public function test_auth_header_is_sent(): void
    {
        Http::fake([
            'http://localhost:7557/devices*' => Http::response([], 200),
        ]);

        $this->client->devices();

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization')
                && str_contains(implode(',', $request->header('Authorization')), 'Basic');
        });
    }

    // ── Error handling ─────────────────────────────────────

    public function test_401_throws_authentication_exception(): void
    {
        Http::fake([
            'http://localhost:7557/*' => Http::response(null, 401),
        ]);

        $this->expectException(GenieACSAuthenticationException::class);

        $this->client->devices();
    }

    public function test_403_throws_authentication_exception(): void
    {
        Http::fake([
            'http://localhost:7557/*' => Http::response(null, 403),
        ]);

        $this->expectException(GenieACSAuthenticationException::class);

        $this->client->devices();
    }

    public function test_500_throws_api_exception(): void
    {
        Http::fake([
            'http://localhost:7557/*' => Http::response(null, 500),
        ]);

        $this->expectException(GenieACSApiException::class);

        $this->client->presets();
    }
}
