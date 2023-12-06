<?php

namespace App\Http\Livewire;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class XAcc extends Component
{
    public $chartData;
    public $apiData;
    public $initialData;
    public $selectedSensor = 0;
    public $sensorData;
    public $machineData;
    public $sensorNames;
    public $machineName;
    public $latestTimestamp;
    public $latestTemp;
    public $xAalarm;
    public $xAwarn;
    public $xAbase;
    public function mount()
    {
        $response = Http::get('http://172.31.2.124:5000/cbmdata/rawdata');

        $machineResponse = Http::get('http://172.31.2.124:5000/cbmdata/compressorlist');
        $this->machineData = $machineResponse->json();

        $sensordata = Http::get('http://172.31.2.124:5000/cbmdata/sensorlist');
        $this->sensorData = $sensordata->json();


        if (!empty($this->sensorData)) {
            $this->sensorNames = collect($this->sensorData)->pluck('sensorName')->toArray();

            $firstKey = key($this->sensorData);
            $this->selectedSensor = $firstKey;

            // Replace machineID with machineName in the sensor data
            $this->sensorData = collect($this->sensorData)->map(function ($sensor) {
                $machineID = $sensor['machineID'] ?? null;
                $sensor['machineName'] = $this->machineData[$machineID]['compressorname'] ?? '';
                unset($sensor['machineID']);
                return $sensor;
            })->toArray();

            $this->machineName = collect($this->sensorData)->pluck('machineName')->toArray();

           
        }
        //dd($this->sensorData);

        $this->apiData = $response->json();
    }
    public function sensor($selectedSensor)
    {
        $chartData = [];
        $latestTimestamp = null;

        foreach ($this->apiData as $entry) {
            if (isset($entry['sensors'][$selectedSensor]['data'])) {
                foreach ($entry['sensors'][$selectedSensor]['data'] as $dataPoint) {
                    $timestamp = Carbon::parse($dataPoint['timestamp']);
                    //if (Carbon::parse($timestamp) >= "2023-12-05 12:00:00") {
                    if (!$latestTimestamp || $timestamp->diffInMinutes($latestTimestamp) >= 5) {
                        $xacc = $dataPoint['x-acc'];
                        $chartData[] = ['x' => $timestamp->format('M d y H:i'), 'y' => $xacc];
                        $latestTimestamp = $timestamp;
                        $xacclatest = $xacc;
                    }else{
                        $this->xAalarm = $dataPoint['x-acc-alarm'];
                        $this->xAwarn = $dataPoint['x-acc-warning'];
                        $this->xAbase = $dataPoint['x-acc-baseline'];
                    }
                    //}

                }
            }
        }

  
        return $chartData;
    }
    public function selectedSensor()
    {
        $chartData = $this->sensor($this->selectedSensor);
        $this->emit('sensorDataUpdated', $chartData,$this->xAalarm, $this->xAwarn, $this->xAbase
        );
    }

    public function render()
    {

    $chartData = $this->sensor($this->selectedSensor);

        return view('livewire.x-acc',[
            'data' => $chartData,
            'sensorNames' => $this->sensorNames,
            'machineName' => $this->machineName,
        ]);
    }
}
