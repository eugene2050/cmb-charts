<?php

namespace App\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class XVel extends Component
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
  
    public $xValarm;
    public $xVwarn;
    public $xVbase;
    public $latestXvel;
    public $xVelTime;
    public $start_date;
    public $end_date;
    public $slider_value;
    public $olddata;

    protected $listeners = ['dateRangeChanged', 'sliderValueChanged'];

    public function mount()
    {
        if (empty($this->start_date)) {
            $this->start_date = now()->startOfWeek()->toDateString();
        }
        if (empty($this->end_date)) {
            $this->end_date = now()->endOfWeek()->toDateString();
        }
        $this->slider_value = "00:00:00";
        $chartData = $this->sensor($this->selectedSensor, $this->start_date, $this->end_date);
        $this->emit('sensorDataUpdated', $chartData, $this->xValarm, $this->xVwarn, $this->xVbase);
    }
    public function getSensorData($sensor)
    {
        $chartData = [];
      
        $response = Http::get('http://172.31.2.124:5000/cbmdata/rawdata?sensor_ids=' . $sensor);
        $this->apiData = $response->json();
        foreach ($this->apiData as $entry) {
            if (isset($entry['sensors'][$sensor]['data'])) {
                foreach ($entry['sensors'][$sensor]['data'] as $dataPoint) {
                    $timestamp = Carbon::parse($dataPoint['timestamp']);
                        $xvel = $dataPoint['x-vel'];
                       
                }
                $chartData[] = ['x' => $timestamp->format('M d y H:i'), 'y' => $xvel];
            }
        }
        return $chartData;
    }
    public function dateRangeChanged()
    {
        $chartData = $this->sensor($this->selectedSensor, $this->start_date, $this->end_date);
        $this->emit('sensorDataUpdated', $chartData,$this->xValarm ,$this->xVwarn, $this->xVbase, $this->latestXvel, $this->xVelTime);
    }
    public function sliderValueChanged($value)
    {
        $this->slider_value = $value;
        $this->dateRangeChanged();
    }
    public function updated($propertyName)
    {
        if ($propertyName === 'selectedMachine') {
            $this->selectedSensor = null;
            $this->emit('machineChanged', $this->selectedMachine);
        } elseif ($propertyName === 'selectedSensor' || $propertyName === 'start_date' || $propertyName === 'end_date') {
            $this->dateRangeChanged();
        }
    }

    public function sensor($selectedSensor, $start_date, $end_date)
    {
        $chartData = [];
        $latestTimestamp = null;
        $start_date = $start_date ." ". $this->slider_value;

        $response = Http::get('http://172.31.2.124:5000/cbmdata/rawdata?sensor_ids=' . $selectedSensor . '&start_date='.$start_date .'&end_date='. $end_date .' 00:00:00');
        $this->apiData = $response->json();
        foreach ($this->apiData as $entry) {
            if (isset($entry['sensors'][$selectedSensor]['data'])) {
                foreach ($entry['sensors'][$selectedSensor]['data'] as $dataPoint) {
                    $timestamp = Carbon::parse($dataPoint['timestamp']);
                    //if (Carbon::parse($timestamp) >= "2023-12-05 12:00:00") {
                    if (!$latestTimestamp || $timestamp->diffInMinutes($latestTimestamp) >= 5) {
                        $xvel = $dataPoint['x-vel'];
                      
                        $chartData[] = ['x' => $timestamp->format('M d y H:i'), 'y' => $xvel];
                        $latestTimestamp = $timestamp;
                    }else{
            
                    }
                    //}
                  
                }
                $this->xValarm = $dataPoint['x-vel-alarm'];
                $this->xVwarn = $dataPoint['x-vel-warning'];
                $this->xVbase = $dataPoint['x-vel-baseline'];
                $this->latestXvel = $dataPoint['x-vel'];
                $this->olddata = ['x' => $timestamp->format('M d y H:i'), 'y' => $xvel];

                $this->xVelTime = $timestamp->format('M d y H:i');
            }
        }

   
        return $chartData;
    }
    public function selectedSensor()
    {
        $chartData = $this->sensor($this->selectedSensor, $this->start_date, $this->end_date);
        $this->emit('sensorDataUpdated', $chartData,$this->xValarm ,$this->xVwarn, $this->xVbase, $this->latestXvel, $this->xVelTime);
    }

    public function render()
    {
        $chartData = $this->sensor($this->selectedSensor, $this->start_date, $this->end_date);
        return view('livewire.x-vel', [
            'data' => $chartData,
            'sensorNames' => $this->sensorNames,
            'machineName' => $this->machineName,

        ]);
    }
}
