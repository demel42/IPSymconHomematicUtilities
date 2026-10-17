<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HmIPRGBW extends IPSModule
{
    use HomematicUtilities\StubsCommonLib;
    use HomematicUtilitiesLocalLib;

    private $SemaphoreID;
    private $SemaphoreTM;

    private static $ModuleActivityLines = 200;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
        $this->SemaphoreTM = 30 * 1000;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('chan0_instID', 0);
        $this->RegisterPropertyInteger('chan1_instID', 0);

        $this->RegisterAttributeString('wake_up_light', json_encode([]));

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('TimerWakeUpLight', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "LoopWakeUpLight", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $instID = $this->ReadPropertyInteger('chan1_instID');
            foreach (['HUE', 'SATURATION', 'LEVEL'] as $ident) {
                @$varID = IPS_GetObjectIDByIdent($ident, $instID);
                if (IPS_VariableExists($varID)) {
                    $this->RegisterMessage($varID, VM_UPDATE);
                }
            }
        }

        if (IPS_GetKernelRunlevel() == KR_READY && $message == VM_UPDATE && $data[1] == true /* changed */) {
            $this->SyncFromDevice();
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $instID = $this->ReadPropertyInteger('chan0_instID');
        if (IPS_InstanceExists($instID) == false) {
            $this->SendDebug(__FUNCTION__, '"chan0_instID" must be defined', 0);
            $field = $this->Translate('Instance of Channel 0');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }
        $instID = $this->ReadPropertyInteger('chan1_instID');
        if (IPS_InstanceExists($instID) == false) {
            $this->SendDebug(__FUNCTION__, '"chan1_instID" must be defined', 0);
            $field = $this->Translate('Instance of Channel 1');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = [
            'chan0_instID',
            'chan1_instID',
        ];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1;

        $this->MaintainVariable('Color', $this->Translate('Color'), VARIABLETYPE_INTEGER, '~HexColor', $vpos++, true);
        $this->MaintainAction('Color', true);
        $this->MaintainVariable('ColorTemp', $this->Translate('Color temperatur'), VARIABLETYPE_INTEGER, 'HmIPRGBW.Kelvin', $vpos++, true);
        $this->MaintainAction('ColorTemp', true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $instID = $this->ReadPropertyInteger('chan1_instID');
            foreach (['HUE', 'SATURATION', 'LEVEL'] as $ident) {
                @$varID = IPS_GetObjectIDByIdent($ident, $instID);
                if (IPS_VariableExists($varID)) {
                    $this->RegisterMessage($varID, VM_UPDATE);
                }
            }

            $this->SyncFromDevice();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('HmIP RGBW');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'         => 'RowLayout',
            'items'        => [
                [
                    'type'         => 'Label',
                    'caption'      => 'Homematic instances',
                ],
                [
                    'type'         => 'SelectInstance',
                    'validModules' => ['{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}'],
                    'name'         => 'chan1_instID',
                    'caption'      => 'Channel 1'
                ],
                [
                    'type'         => 'SelectInstance',
                    'validModules' => ['{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}'],
                    'name'         => 'chan0_instID',
                    'caption'      => 'Channel 0'
                ],
            ],
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();
        $formActions[] = $this->GetModuleActivityFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'LoopWakeUpLight':
                $this->LoopWakeUpLight();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            case 'Color':
                $r = $this->SetColor((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $this->bool2str($r), 0);
                break;
            case 'ColorTemp':
                $r = $this->SetColorTemp((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $this->bool2str($r), 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function SyncFromDevice()
    {
        $instID = $this->ReadPropertyInteger('chan1_instID');

        @$varID = IPS_GetObjectIDByIdent('HUE', $instID);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'device-variable "HUE" missing', 0);
            return;
        }
        $h = GetValue($varID);

        @$varID = IPS_GetObjectIDByIdent('SATURATION', $instID);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'device-variable "SATURATION" missing', 0);
            return;
        }
        $s = (int) (GetValue($varID) * 100);

        @$varID = IPS_GetObjectIDByIdent('LEVEL', $instID);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'device-variable "LEVEL" missing', 0);
            return;
        }
        $v = (int) (GetValue($varID) * 100);

        $rgb = $this->HSV2RGB($h, $s, $v);

        $this->SendDebug(__FUNCTION__, 'H=' . $h . ', S=' . $s . ', V=' . $v . ' => R=' . $rgb['R'] . ', G=' . $rgb['G'] . ', B=' . $rgb['B'], 0);

        $c = $this->RGB2Val($rgb['R'], $rgb['G'], $rgb['B']);
        if ($c != $this->GetValue('Color')) {
            $this->SetValue('Color', $c);
        }

        $k = $this->RGB2Kelvin($rgb['R'], $rgb['G'], $rgb['B']);
        if ($k != $this->GetValue('ColorTemp')) {
            $this->SetValue('ColorTemp', $k);
        }
    }

    public function SetColor(int $val)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $rgb = $this->Val2RGB($val);
        $hsv = $this->RGB2HSV($rgb['R'], $rgb['G'], $rgb['B']);
        $r = $this->SendCombinedParameter($hsv['V'], $hsv['H'], $hsv['S']);

        return $r;
    }

    public function SetColorTemp(int $val)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $instID = $this->ReadPropertyInteger('chan1_instID');
        @$varID = IPS_GetObjectIDByIdent('LEVEL', $instID);
        if ($varID == false) {
            $level = 100;
        } else {
            $level = (int) (GetValue($varID) * 100);
        }

        $hsv = $this->Kelvin2HSV($val, $level);
        $r = $this->SendCombinedParameter($hsv['V'], $hsv['H'], $hsv['S']);

        return $r;
    }

    public function SendCombinedParameter(int $level, int $hue, int $saturation)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        if ($level < 0 || $level > 100) {
            $this->SendDebug(__FUNCTION__, 'level $level ist not between 0..100 %', 0);
            return false;
        }
        if ($hue < 0 || $hue > 360) {
            $this->SendDebug(__FUNCTION__, 'hue $hue ist not between 0..360 °', 0);
            return false;
        }
        if ($saturation < 0 || $saturation > 100) {
            $this->SendDebug(__FUNCTION__, 'saturation $saturation ist not between 0..100 %', 0);
            return false;
        }

        $instID = $this->ReadPropertyInteger('chan1_instID');
        $ident = 'COMBINED_PARAMETER';

        $paramV = [];
        $paramV[] = 'L=' . $level;
        $paramV[] = 'H=' . $hue;
        $paramV[] = 'SAT=' . $saturation;

        $paramV[] = 'OT=0'; // Einschaltzeit
        $paramV[] = 'RT=0'; // Zampenzeit (Sec)
        $paramV[] = 'RTTOV=0'; // Rampen-Ausschaltzeit
        $paramV[] = 'RTTOU=0'; // Einheit von RTTOV (0=sec, 1=min, 3=msec)

        $paramS = implode(',', $paramV);

        $r = @HM_WriteValueString($instID, $ident, $paramS);
        if ($r == false) {
            $msg = 'HM_WriteValueString(' . $instID . ', \'' . $ident . '\', ' . $paramS . ') failed';
            $this->SendDebug(__FUNCTION__, $msg, 0);
            return false;
        } else {
            $msg = 'HM_WriteValueString(' . $instID . ', \'' . $ident . '\', ' . $paramS . ') succed';
            $this->SendDebug(__FUNCTION__, $msg, 0);
            return true;
        }
    }

    // Berechnung eines Lichtwecker-Schrittes im HSV-Farbraum und RGBW-Werten
    // progress 0..1
    public function calcLightWakeupStep(float $progress)
    {
        // Grenzen absichern
        $progress = max(0.0, min(1.0, $progress));

        // HSV-KURVEN DESIGNEN
        // Hue (H): Wandert von 0° (Tiefrot) zu ca. 50° (Warmgelb)
        $h = 0 + ($progress * 50);

        // Value/Brightness (V): Steigt quadratisch für ein sanfteres Aufwachen im dunklen Bereich
        $v = pow($progress, 2) * 100;

        // Saturation (S): Bleibt lange intensiv, fällt erst in der zweiten Hälfte stark ab für echtes Weiß
        if ($progress < 0.5) {
            $s = 100;
        } else {
            // Linearer Abfall von 100 auf 0 in der zweiten Hälfte
            $s = 100 - (($progress - 0.5) * 2 * 100);
        }

        return [
            'H' => round($h, 1),
            'S' => round($s, 1),
            'V' => round($v, 1),
        ];
    }

    public function calculateSunrise(float $progress, float $maxBrightness = 1.0)
    {
        // 1. Eingrenzung des Fortschritts zur Sicherheit
        $progress = max(0.0, min(1.0, $progress));
        $maxBrightness = max(0.0, min(1.0, round($maxBrightness / 100)));

        // 2. Nicht-lineare Kurve für die Helligkeit (Value) verwenden (Quadratischer Anstieg)
        // Das sorgt dafür, dass es am Anfang sehr sanft hell wird.
        // maxBrightness begrenzt die Helligkeit
        if ($progress == 0.0 || $maxBrightness == 0.0) {
            $v = 0.0;
        } else {
            $minBrightness = 0.04; // Startet sofort bei ca. 4% Helligkeit

            // Falls das gewünschte Maximum unter dem Minimum liegt, passen wir das Minimum an
            if ($minBrightness >= $maxBrightness) {
                $minBrightness = $maxBrightness * 0.2;
            }

            // Mathematische Mischkurve für den sanften Verlauf (0.0 bis 1.0)
            $kurve = (0.4 * $progress) + (0.6 * pow($progress, 2));

            // Skalierung exakt ZWISCHEN dem Minimum und dem dynamischen Maximum
            $v = $minBrightness + ($kurve * ($maxBrightness - $minBrightness));
        }

        // 3. Berechnung des Farbtons (Hue: 0 - 360)
        // Pfad: Nachtblau (240°) -> Rot (360°/0°) -> Orange (30°) -> Gelb/Warmweiß (55°)
        if ($progress < 0.4) {
            // Erste Phase (Dämmerung): Von Blau (240°) fließend zu reinem Rot (360°)
            $norm = $progress / 0.4;
            $h = 240 + ($norm * 120);
        } else {
            // Zweite Phase (Aufgang): Von Rot (0°) zu Sonnengelb/Warmweiß (55°)
            $norm = ($progress - 0.4) / 0.6;
            $h = 0 + ($norm * 55);
        }

        // 4. Berechnung der Sättigung (Saturation: 0.0 - 1.0)
        // Bleibt am Anfang hoch für kräftige Dämmerungsfarben und fällt am Ende ab
        if ($progress < 0.6) {
            $s = 1.0;
        } else {
            // Entsättigung am Ende für helles, weißeres Licht
            $norm = ($progress - 0.6) / 0.4;
            $s = 1.0 - ($norm * 0.65); // Endet bei ca. 0.35 Sättigung
        }

        return [
            'H' => (int) round($h),
            'S' => (int) round($s * 100),
            'V' => (int) round($v * 100),
        ];
    }

    public function StartWakeUpLight(int $minutes, int $target_brightness)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->SemaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $paramV = @json_decode((string) $this->ReadAttributeString('wake_up_light'), true);
        $step = (int) $this->GetArrayElem($paramV, 'step', 0);
        if ($step > 0) {
            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'almost active WakeUp  step=' . $step . ', parameters=' . print_r($paramV, true), 0);
            return false;
        }

        $step = 0;
        $start_ts = time();
        $duration = $minutes * 60;
        $step_duration = 30;
        $n_steps = round($duration / $step_duration);

        $paramV = [
            'step'              => $step,
            'start_ts'          => $start_ts,
            'duration'          => $duration,
            'step_duration'     => $step_duration,
            'n_steps'           => $n_steps,
            'target_brightness' => $target_brightness,
        ];

        $this->WriteAttributeString('wake_up_light', json_encode($paramV));

        $secs = $step * $step_duration;
        $progress = $secs > 0 ? $secs / $duration : 0;

        $hsv = $this->calculateSunrise($progress, $target_brightness);
        $r = $this->SendCombinedParameter($hsv['V'], $hsv['H'], $hsv['S']);
        $this->SendDebug(__FUNCTION__, 'step=' . $step . ', progress=' . ($progress * 100) . '%, hsv=' . print_r($hsv, true) . ', SendCombinedParameter() ' . ($r ? 'succeeded' : 'failed'), 0);
        if ($r) {
            $paramV['hsv'] = $hsv;
        }

        $step++;
        $paramV['step'] = $step;
        $this->WriteAttributeString('wake_up_light', json_encode($paramV));

        $delay = $start_ts + ($step * $step_duration) - time();
        if ($delay <= 0) {
            $delay = $step_duration;
        }
        $this->MaintainTimer('TimerWakeUpLight', $delay * 1000);

        $msg = 'start WakeUpLight with H=' . $hsv['H'] . ', S=' . $hsv['S'] . ', L=' . $hsv['V'];
        if ($r == false) {
            $msg .= ', transmission failed';
        }
        $this->AddModuleActivity($msg, self::$ModuleActivityLines);

        IPS_SemaphoreLeave($this->SemaphoreID);
        return true;
    }

    public function LoopWakeUpLight()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->SemaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $paramV = @json_decode((string) $this->ReadAttributeString('wake_up_light'), true);
        $step = (int) $this->GetArrayElem($paramV, 'step', 0);
        if ($step == 0) {
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainTimer('TimerWakeUpLight', 0);

            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'no active WakeUp', 0);
            return false;
        }

        $n_steps = (int) $this->GetArrayElem($paramV, 'n_steps', 0);
        if ($step > $n_steps) {
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainTimer('TimerWakeUpLight', 0);

            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'already completed WakeUp', 0);
            return false;
        }

        $start_ts = (int) $this->GetArrayElem($paramV, 'start_ts', 0);
        $step_duration = (int) $this->GetArrayElem($paramV, 'step_duration', 0);
        $duration = (int) $this->GetArrayElem($paramV, 'duration', 0);
        $target_brightness = (int) $this->GetArrayElem($paramV, 'target_brightness', 0);

        $secs = $step * $step_duration;
        $progress = $secs > 0 ? $secs / $duration : 0;

        $hsv = $this->calculateSunrise($progress, $target_brightness);
        $r = $this->SendCombinedParameter($hsv['V'], $hsv['H'], $hsv['S']);
        $this->SendDebug(__FUNCTION__, 'step=' . $step . ', progress=' . ($progress * 100) . '%, hsv=' . print_r($hsv, true) . ', SendCombinedParameter() ' . ($r ? 'succeeded' : 'failed'), 0);
        if ($r) {
            $paramV['hsv'] = $hsv;
        }

        $step++;
        $paramV['step'] = $step;
        if ($step > $n_steps) {
            $this->SendDebug(__FUNCTION__, 'completed WakeUp', 0);
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $msg = 'finished WakeUpLight step=' . ($step - 1) . ', progress=' . ($progress * 100) . '% with H=' . $hsv['H'] . ', S=' . $hsv['S'] . ', L=' . $hsv['V'];
        } else {
            $this->SendDebug(__FUNCTION__, 'continue WakeUp', 0);
            $this->WriteAttributeString('wake_up_light', json_encode($paramV));
            $delay = $start_ts + ($step * $step_duration) - time();
            if ($delay <= 0) {
                $delay = $step_duration;
            }
            $this->MaintainTimer('TimerWakeUpLight', $delay * 1000);
            $msg = 'executed WakeUpLight step=' . ($step - 1) . ', progress=' . ($progress * 100) . '% with H=' . $hsv['H'] . ', S=' . $hsv['S'] . ', L=' . $hsv['V'];
        }

        IPS_SemaphoreLeave($this->SemaphoreID);

        $this->AddModuleActivity($msg, self::$ModuleActivityLines);
        return true;
    }

    public function StopWakeUpLight()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->SemaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $paramV = @json_decode((string) $this->ReadAttributeString('wake_up_light'), true);
        $step = (int) $this->GetArrayElem($paramV, 'step', 0);
        if ($step == 0) {
            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'no active WakeUp', 0);
            return false;
        }

        $this->WriteAttributeString('wake_up_light', json_encode([]));
        $this->MaintainTimer('TimerWakeUpLight', 0);

        IPS_SemaphoreLeave($this->SemaphoreID);

        $this->SendDebug(__FUNCTION__, 'stopped WakeUp', 0);
        $msg = 'stopped WakeUpLight';

        $this->AddModuleActivity($msg, self::$ModuleActivityLines);
        return true;
    }

    // RGB in RGBW umrechnen
    public function RGB2RGBW(int $r, int $g, int $b)
    {
        $w = min($r, $g, $b);

        // Weiß-Anteil von den RGB-Kanälen abziehen, um Mischlicht-Verfälschungen zu verhindern
        $r = $r - $w;
        $g = $g - $w;
        $b = $b - $w;

        return [
            'R' => (int) $r,
            'G' => (int) $g,
            'B' => (int) $b,
            'W' => (int) $w,
        ];
    }

    // HSV in RGB umrechnen
    public function HSV2RGB(float $h, float $s, float $v)
    {
        $s /= 100;
        $v /= 100;

        $c = $v * $s;
        $x = $c * (1 - abs(fmod(($h / 60), 2) - 1));
        $m = $v - $c;

        if ($h >= 0 && $h < 60) {
            [$r, $g, $b] = [$c, $x, 0];
        } elseif ($h >= 60 && $h < 120) {
            [$r, $g, $b] = [$x, $c, 0];
        } elseif ($h >= 120 && $h < 180) {
            [$r, $g, $b] = [0, $c, $x];
        } elseif ($h >= 180 && $h < 240) {
            [$r, $g, $b] = [0, $x, $c];
        } elseif ($h >= 240 && $h < 300) {
            [$r, $g, $b] = [$x, 0, $c];
        } else {
            [$r, $g, $b] = [$c, 0, $x];
        }

        return [
            'R' => (int) round(($r + $m) * 255),
            'G' => (int) round(($g + $m) * 255),
            'B' => (int) round(($b + $m) * 255),
        ];
    }

    // RGB in RGBW umrechnen
    public function HSV2RGBW(float $h, float $s, float $v)
    {
        $rgb = $this->HSV2RGB($h, $s, $v);
        $rgbw = $this->RGB2RGBW($rgb['R'], $rgb['G'], $rgb['B']);

        return $rgbw;
    }

    public function Kelvin2HSV(int $kelvin, int $brightness)
    {
        // 1. Eingrenzen und Vorbereiten für die RGB-Näherung
        if ($kelvin < 1000) {
            $kelvin = 1000;
        } elseif ($kelvin > 40000) {
            $kelvin = 40000;
        }
        $temp = $kelvin / 100;

        // --- Schritt A: Kelvin zu RGB Näherung (Tanner Helland Algorithmus) ---
        // Rot
        if ($temp <= 66) {
            $r = 255;
        } else {
            $r = $temp - 60;
            $r = 329.698727446 * pow($r, -0.1332047592);
        }
        // Grün
        if ($temp <= 66) {
            $g = $temp;
            $g = 99.4708025861 * log($g) - 161.1195681661;
        } else {
            $g = $temp - 60;
            $g = 288.1221695283 * pow($g, -0.0755148492);
        }
        // Blau
        if ($temp >= 66) {
            $b = 255;
        } else {
            if ($temp <= 19) {
                $b = 0;
            } else {
                $b = $temp - 10;
                $b = 138.5177312231 * log($b) - 305.0447927307;
            }
        }

        // RGB-Werte auf 0-255 begrenzen und normalisieren (0.0 bis 1.0)
        $r = max(0, min(255, $r)) / 255;
        $g = max(0, min(255, $g)) / 255;
        $b = max(0, min(255, $b)) / 255;

        // --- Schritt B: RGB zu HSV Konvertierung ---
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        // 1. Hue (Farbwert 0 - 360)
        if ($delta == 0) {
            $h = 0; // Weiß/Graustufe, kein messbarer Farbton
        } elseif ($max == $r) {
            $h = 60 * fmod((($g - $b) / $delta), 6);
        } elseif ($max == $g) {
            $h = 60 * ((($b - $r) / $delta) + 2);
        } else {
            $h = 60 * ((($r - $g) / $delta) + 4);
        }
        if ($h < 0) {
            $h += 360;
        }

        // 2. Saturation (Sättigung 0.0 - 1.0)
        $s = ($max == 0) ? 0 : ($delta / $max);

        // 3.Value (Helligkeit 0 - 100)
        if ($brightness < 0) {
            $v = 0;
        } elseif ($brightness > 100) {
            $v = 100;
        } else {
            $v = $brightness;
        }

        return [
            'H' => (int) round($h),
            'S' => (int) round($s * 100),
            'V' => (int) $brightness,
        ];
    }

    public function Kelvin2RGB(int $kelvin)
    {
        // Eingrenzung auf den sinnvollen Bereich des Algorithmus
        $kelvin = max(1000, min(40000, $kelvin));
        $temp = $kelvin / 100;

        // Rot berechnen
        if ($temp <= 66) {
            $r = 255;
        } else {
            $r = $temp - 60;
            $r = 329.698727446 * pow($r, -0.1332047592);
            $r = max(0, min(255, $r));
        }

        // Grün berechnen
        if ($temp <= 66) {
            $g = $temp;
            $g = 99.4708025861 * log($g) - 161.1195681661;
            $g = max(0, min(255, $g));
        } else {
            $g = $temp - 60;
            $g = 288.1221695283 * pow($g, -0.0755148492);
            $g = max(0, min(255, $g));
        }

        // Blau berechnen
        if ($temp >= 66) {
            $b = 255;
        } else {
            if ($temp <= 19) {
                $b = 0;
            } else {
                $b = $temp - 10;
                $b = 138.5177312231 * log($b) - 305.0447927307;
                $b = max(0, min(255, $b));
            }
        }

        return [
            'R' => (int) round($r),
            'G' => (int) round($g),
            'B' => (int) round($b)
        ];
    }

    // Kelvin in RGBW umrechnen
    public function Kelvin2RGBW(int $kelvin)
    {
        $rgb = $this->Kelvin2RGB($kelvin);
        $rgbw = $this->RGB2RGBW($rgb['R'], $rgb['G'], $rgb['B']);

        return $rgbw;
    }

    // RGB in HSV umrechnen
    public function RGB2HSV(int $r, int $g, int $b)
    {
        // 1. Werte auf den Bereich 0-1 normalisieren
        $rNormal = $r / 255;
        $gNormal = $g / 255;
        $bNormal = $b / 255;

        // 2. Maximum, Minimum und die Differenz (Delta) bestimmen
        $cMax = max($rNormal, $gNormal, $bNormal);
        $cMin = min($rNormal, $gNormal, $bNormal);
        $delta = $cMax - $cMin;

        // 3. Farbton (H) berechnen
        $h = 0;
        if ($delta != 0) {
            if ($cMax == $rNormal) {
                $h = 60 * fmod((($gNormal - $bNormal) / $delta), 6);
            } elseif ($cMax == $gNormal) {
                $h = 60 * ((($bNormal - $rNormal) / $delta) + 2);
            } elseif ($cMax == $bNormal) {
                $h = 60 * ((($rNormal - $gNormal) / $delta) + 4);
            }

            // Negative Winkel korrigieren
            if ($h < 0) {
                $h += 360;
            }
        }

        // 4. Sättigung (S) berechnen
        $s = ($cMax == 0) ? 0 : ($delta / $cMax) * 100;

        // 5. Helligkeit / Wert (V) berechnen
        $v = $cMax * 100;

        // Werte runden und als Array zurückgeben
        return [
            'H' => (int) round($h),
            'S' => (int) round($s),
            'V' => (int) round($v),
        ];
    }

    public function RGB2Kelvin(int $r, int $g, int $b)
    {
        // 1. Normalisierung auf den Bereich 0.0 - 1.0
        $r = $r / 255.0;
        $g = $g / 255.0;
        $b = $b / 255.0;

        // Gamma-Korrektur (sRGB in lineares RGB umwandeln)
        $r = ($r > 0.04045) ? pow(($r + 0.055) / 1.055, 2.4) : ($r / 12.92);
        $g = ($g > 0.04045) ? pow(($g + 0.055) / 1.055, 2.4) : ($g / 12.92);
        $b = ($b > 0.04045) ? pow(($b + 0.055) / 1.055, 2.4) : ($b / 12.92);

        // 2. Umrechnung in den CIE XYZ Farbraum
        $x = $r * 0.4124 + $g * 0.3576 + $b * 0.1805;
        $y = $r * 0.2126 + $g * 0.7152 + $b * 0.0722;
        $z = $r * 0.0193 + $g * 0.1192 + $b * 0.9505;

        // Schutz vor Division durch Null
        if (($x + $y + $z) == 0) {
            return false;
        }

        // 3. Berechnung der CIE xy-Koordinaten
        $cieX = $x / ($x + $y + $z);
        $cieY = $y / ($x + $y + $z);

        // 4. McCamy-Formel zur Schätzung von Kelvin
        // Epizentrum für die Kurve (nach McCamy)
        $xe = 0.3320;
        $ye = 0.1858;

        $n = ($cieX - $xe) / ($ye - $cieY);

        // Polynom-Berechnung
        $kelvin = 449.0 * pow($n, 3) + 3525.0 * pow($n, 2) + 6823.3 * $n + 5520.33;

        // Kelvin-Werte machen physikalisch meist nur zwischen 1.000K und 15.000K Sinn
        if ($kelvin < 0 || $kelvin > 15000) {
            return false;
        }

        return (int) round($kelvin);
    }

    public function HSV2Kelvin(float $h, float $s, float $v)
    {
        $rgb = $this->HSV2RGB($h, $s, $v);
        $kelvin = $this->RGB2Kelvin($rgb['R'], $rgb['G'], $rgb['B']);

        return (int) $kelvin;
    }

    public function Val2RGB(int $val)
    {
        return [
            'R' => ($val >> 16) & 0xFF,
            'G' => ($val >> 8) & 0xFF,
            'B' => $val & 0xFF,
        ];
    }

    public function RGB2Val(int $r, int $g, int $b)
    {
        return ($r << 16) | ($g << 8) | $b;
    }
}
