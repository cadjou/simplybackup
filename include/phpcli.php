<?php
// **********************************************
// **********************************************
// **                                          **
// **         Class phpcli by CaDJoU           **
// **                                          **
// **  How to use : see example.php            **
// **                                          **
// **********************************************
// **********************************************
class phpcli
{
    protected $parameters   = [];
    protected $keyParams    = [];
    protected $keyPossible  = [];
    protected $forHelp      = [];
    protected $styleBox     = [];
    protected $nameCode     = '';
    protected $description  = '';
    
    public function __construct($nameCode,$description,$keyParams,$styleBox=[])
    {
        $this->nameCode    = $nameCode;
        $this->description = $description;
        $this->styleBox    = $styleBox;
        
        $this->manageParams($keyParams);
        $this->manageUserKey($this->keyPossible);
    }

    public function doAction()
    {
        if (empty($this->parameters['_action']))
        {
            echo 'No action define : ' . "\n";
            return null;
        }
        $action = $this->parameters['_action'];
        if (method_exists($this,'on' . $action))
        {
            return call_user_func([$this,'on' . $action]);
        }
        return call_user_func([$this,'onhelp']);
    }
    
    protected function manageParams($keyParams)
    {
        $keyPossible = [];
        $keyParamsT  = [];
        $parameters  = [];
        foreach($keyParams as $key=>$param)
        {
            $param['keyWord'] = $param['keyWord'] ?? substr($key,1);
            $keyWord = $param['keyWord'];
            if (isset($keyPossible[$keyWord]))
            {
                continue;
            }
            
            $n = 1;
            $keyShortName = substr($keyWord,0,$n);
            while(isset($keyPossible[$keyShortName]) and $keyShortName != $keyWord)
            {
                $n++;
                $keyShortName = substr($keyWord,0,$n);
            }
            if ($keyShortName == $keyWord)
            {
                continue;
            }
            
            $type = substr($key,0,1);
            if($type == '$' and isset($param['default']))
            {
                $parameters[$keyWord] = $param['default'];
            }
            
            $forHelp[$key]              = ['-' . $keyShortName , '-' . $keyWord];
            $keyParamsT[$key]           = $param;
            $keyPossible[$keyWord]      = $key;
            $keyPossible[$keyShortName] = $key;
        }
        $this->keyParams    = $keyParamsT;
        $this->keyPossible  = $keyPossible;
        $this->parameters   = $parameters;
        $this->forHelp      = $forHelp;
        return $keyPossible;
    }
    
    protected function manageUserKey()
    {
        global $argv;
        $valueForHelp = ['-h','--h','-help','--help'];

        $tableParams  = $this->parameters;
        $tableParams['_action'] = $action = 'none';
        foreach($argv as $key=>$value)
        {
            if (isset($this->keyPossible[$value]))
            {
                $type = substr($this->keyPossible[$value],0,1);
                $keyAction = substr($this->keyPossible[$value],1);
                if ($type == '_')
                {
                    if ($action)
                    {
                        echo 'Action already defined : ' . $action . "\n";
                    }
                    $action = $keyAction;
                }
                elseif($type == '$')
                {
                    $val = isset($argv[$key + 1]) ? $argv[$key + 1] : false;
                    if (!$val)
                    {
                        echo 'Action already defined : ' . $action . "\n";
                    }
                    $tableParams[$keyAction] = $val;
                    next($argv);
                }
            }
            // For Help
            elseif (in_array($value,$valueForHelp))
            {
                $action = 'help';
                break;
            }
        }
        $tableParams['_action'] = $action;
        $this->parameters = $tableParams;
        
    }
        
    protected function borderBox($text,$style = [])
    {
        // Value by default
        $key            = $style['key']            ??  '#';
        $margin         = $style['margin']         ??  '1';
        $border         = $style['border']         ??  '2';
        $padding        = $style['padding']        ??  '1';

        $style['key-top']        = $style['key-top']        ?? $key;     $key_top        = $style['key-top']       ;
        $style['key-bottom']     = $style['key-bottom']     ?? $key;     $key_bottom     = $style['key-bottom']    ;
        $style['key-right']      = $style['key-right']      ?? $key;     $key_right      = $style['key-right']     ;
        $style['key-left']       = $style['key-left']       ?? $key;     $key_left       = $style['key-left']      ;
        $style['key-corner-lt']  = $style['key-corner-lt']  ?? $key;     $key_corner_lt  = $style['key-corner-lt'] ;
        $style['key-corner-rt']  = $style['key-corner-rt']  ?? $key;     $key_corner_rt  = $style['key-corner-rt'] ;
        $style['key-corner-lb']  = $style['key-corner-lb']  ?? $key;     $key_corner_lb  = $style['key-corner-lb'] ;
        $style['key-corner-rb']  = $style['key-corner-rb']  ?? $key;     $key_corner_rb  = $style['key-corner-rb'] ;
        
        $style['margin-top']     = $style['margin-top']     ?? $margin;  $margin_top     = $style['margin-top']    ;
        $style['margin-bottom']  = $style['margin-bottom']  ?? $margin;  $margin_bottom  = $style['margin-bottom'] ;
        $style['margin-right']   = $style['margin-right']   ?? $margin;  $margin_right   = $style['margin-right']  ;
        $style['margin-left']    = $style['margin-left']    ?? $margin;  $margin_left    = $style['margin-left']   ;
        
        $style['border-top']     = $style['border-top']     ?? $border;  $border_top     = $style['border-top']    ;
        $style['border-bottom']  = $style['border-bottom']  ?? $border;  $border_bottom  = $style['border-bottom'] ;
        $style['border-right']   = $style['border-right']   ?? $border;  $border_right   = $style['border-right']  ;
        $style['border-left']    = $style['border-left']    ?? $border;  $border_left    = $style['border-left']   ;
        
        $style['padding-top']    = $style['padding-top']    ?? $padding; $padding_top    = $style['padding-top']   ;
        $style['padding-bottom'] = $style['padding-bottom'] ?? $padding; $padding_bottom = $style['padding-bottom'];
        $style['padding-right']  = $style['padding-right']  ?? $padding; $padding_right  = $style['padding-right'] ;
        $style['padding-left']   = $style['padding-left']   ?? $padding; $padding_left   = $style['padding-left']  ;
        
        $text_align      = $style['text-align']    ?? 'left';
        
        $tableText = is_string($text)     ? explode("\n",$text) : $text;
        $tableText = is_array($tableText) ? $tableText          : [];
        
        $maxLenText = 0;
        $tableTextUpdate = [];
        foreach($tableText as $nLine=>$line)
        {
            $tableTab = explode("\t",$line);
            if (count($tableTab) > 1)
            {
                $lineUpdate = '';
                foreach($tableTab as $part)
                {
                    $tablePart  = str_split($part,16);
                    $ncLastPart = strlen($tablePart[count($tablePart)-1]);
                    $tablePart[count($tablePart)-1] .= str_repeat(' ',16-$ncLastPart);
                    $lineUpdate .= implode('',$tablePart);
                }
                $line = $lineUpdate;
            }
            $tableTextUpdate[$nLine] = $line;
            $maxLenText = max(strlen($line),$maxLenText);
        }
        $styleLineLeft  = @str_repeat(' ',$margin_left)   . @str_repeat($key_left,$border_left)  . @str_repeat(' ',$padding_left);
        $styleLineRight  = @str_repeat(' ',$padding_right) . @str_repeat($key_right,$border_right) . @str_repeat(' ',$margin_right);
        $tableLines = [];
        foreach($tableTextUpdate as $nLine=>$line)
        {
            $nc = strlen($line);
            if ($text_align == 'left')
            {
                $line = $line . @str_repeat(' ', $maxLenText - $nc);
            }
            elseif ($text_align == 'center')
            {
                $line = @str_repeat(' ', floor(($maxLenText - $nc)/2)) . $line . @str_repeat(' ', ceil(($maxLenText - $nc)/2));
            }
            elseif ($text_align == 'right')
            {
                $line = @str_repeat(' ', $maxLenText - $nc) . $line;
            }
            
            $line = $styleLineLeft . $line . $styleLineRight;
            $maxLineLen = strlen($line);
            
            $tableLines[] = $line;
        }
        
        $borderPrintTop  = @str_repeat(' ', $margin_left) . @str_repeat($key_top,$maxLineLen - $margin_left - $margin_right) . @str_repeat(' ', $margin_right);
        $paddingPrintTop = $styleLineLeft . @str_repeat(' ',$maxLenText) . $styleLineRight;
        
        $paddingPrintBottom = $styleLineLeft . @str_repeat(' ',$maxLenText) . $styleLineRight;
        $borderPrintBottom  = @str_repeat(' ', $margin_left) . @str_repeat($key_bottom,$maxLineLen - $margin_left - $margin_right) . @str_repeat(' ', $margin_right);
        
        $print = array_fill(0,$margin_top,'');
        $print = array_merge($print,array_fill(0,$border_top,$borderPrintTop));
        $print = array_merge($print,array_fill(0,$padding_top,$paddingPrintTop));
        $print = array_merge($print,$tableLines);
        $print = array_merge($print,array_fill(0,$padding_bottom,$paddingPrintBottom));
        $print = array_merge($print,array_fill(0,$border_bottom,$borderPrintBottom));
        $print = array_merge($print,array_fill(0,$margin_bottom,''));
        
        return implode("\n",$print);
        
    }

    protected function onhelp()
    {
        $forPrint = $box = [];
        
        ksort($this->forHelp);
        foreach($this->forHelp as $key=>$data)
        {
            $type = substr($key,0,1);
            $forPrint[$type][$key] = implode(', ',$data) . "\t" . $this->keyParams[$key]['help'];
        }
        
        $box[] = $this->nameCode;
        $box[] = $this->description;
        $box[] = '';
        $box[] = '*** Powered by cad-phpcli --> https://github.com/cadjou/cad-phpcli ***';
        $box[] = '';
        
        $number = $n = 0;
        if (isset($forPrint['_']))
        {
            $box[] = 'Actions availables :';
            $box[] = $n . "\tExit";
            
            foreach($forPrint['_'] as $key=>$data)
            {
                $n ++;
                $box[] = $n . "\t" . $data;
                $question[$n] = $key;
            }
            $box[] = '';
        }
        if (isset($forPrint['$']))
        {
            $box[] = 'Parameters availables :';
            foreach($forPrint['$'] as $key=>$data)
            {
                $n ++;
                $box[] = $n . "\t" . $data;
                $question[$n] = $key;
            }
        }
        echo $this->borderBox($box,$this->styleBox);
        return $question;
    }
   
    protected function onnone()
    {
        $question = $this->onhelp();
        echo 'Use number to execute or to set. ';
        $n = max(array_flip($question));
        $exit = false;
        while (!$exit)
        {
            $number = 0;
            echo 'So (0-' . $n . ') ? ' . "\n";
            fscanf(STDIN, "%d\n", $number);
            if (!$number or empty($question[$number]))
            {
                $exit = true;
                return;
            }
            else
            {
                $key = $question[$number];
                $type = substr($key,0,1);
                if ($type == '_')
                {
                    $this->parameters['_action'] = substr($key,1);
                    $this->doAction();
                }
                if ($type == '$')
                {
                    $param = $this->keyParams[$key]['keyWord'];
                    $doChange = 'y';
                    if (isset($this->parameters[$param]))
                    {
                        echo 'Parameter : ' . $param . ' = ' . $this->parameters[$param] . "\n";
                        echo 'You want to change ? (N/y)' . "\n";
                        $stdin = fopen('php://stdin', 'r');
                        $doChange = trim(fgetc($stdin));
                        
                    }
                    if (strtolower(substr($doChange,0,1)) == 'y')
                    {
                        echo 'Set ' . $param . ' : ';
                        $answer = trim(fgets(STDIN));
                        $this->parameters[$param] = $answer;
                        echo "\n";
                        echo 'Parameter : ' . $param . ' = ' . $answer;
                        echo "\n";
                    }
                }
            }
        }
    }
}