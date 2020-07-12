<?php
require __DIR__ . '/include/phpcli.php';

class simplyBackup extends phpcli
{
            //            Right        Link         Group        User         Size         Date         Hour        File
    private $regLS = '/^(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(.*)$/m';
    private $dirApp = [];
    private $dirSep = DIRECTORY_SEPARATOR;
    
    
    function onmakebackup()
    {
        $this->initFolder();
        
        $config = [];
        foreach(array_slice(scandir($this->dirApp['config']), 2) as $file)
        {
            if (pathinfo ($file,PATHINFO_EXTENSION) == 'php')
            {
                include $this->dirApp['config'] . $file;
            }
        }
        
        foreach($config as $name=>$data)
        {
            $this->doBackup($name,$data);
        }
    }
    function initFolder()
    {
        $docRoot          = __DIR__         . $this->dirSep;
        $dir['config']    = $docRoot        . 'config' . $this->dirSep;
        $dir['db']        = $docRoot        . 'db'     . $this->dirSep;
        $dir['tmp']       = $docRoot        . 'tmp'    . $this->dirSep;
        $dir['tmpBackup'] = $dir['tmp']     . 'backup' . $this->dirSep;
        $dir['tmpLs']     = $dir['tmp']     . 'ls'     . $this->dirSep;
        $dir['tmpList']   = $dir['tmp']     . 'list'   . $this->dirSep;
        
        foreach($dir as $name=>$path)
        {
            if ($this->createFolder($path))
            {
                $this->dirApp[$name] = $path;
            }
            else
            {
                echo $this->borderBox('Error : the folder : ' . $path . ' cannot be created',$this->styleBox);
                exit();
            }
        }
    }
    function doBackup($nameBackup,$data)
    {
        $toBackupLocation  = rtrim(str_replace('\\','/',$data['toBackup']['location']),'/') . '/';
        $storageType       = $data['storage'] ['type'];
        $storageLocation   = rtrim(str_replace('\\','/',$data['storage'] ['location']),'/') . '/';
        $storageKeyCrypt   = $data['storage'] ['keyCrypt'];
        $sizeGroup         = 50000000;
        
        $dbFiles = $this->loadFile($this->dirApp['db'] . $nameBackup . '_Files.db',$storageKeyCrypt);
        if (!is_array($dbFiles))
        {
            $dbFiles = [];
        }
        $dbBackup = $this->loadFile($this->dirApp['db'] . $nameBackup . '_Backup.db',$storageKeyCrypt);
        if (!is_array($dbBackup) or !$dbBackup)
        {
            $dbBackup = [];
            $idBackup = 1;
        }
        if ($dbBackup)
        {
            end($dbBackup);
            $idBackup = key($dbBackup) + 1;
        }
        $dbBackup[$idBackup] = date('YmdHis');
        
        // saveFile($dirDb . $nameBackup . '_Backup.db',$dbBackup,$storageKeyCrypt);
        $dbFiles = $this->doUpdateDbFiles($idBackup,$dbFiles,$toBackupLocation);
        list($filesToBackup,$dbFiles) = $this->makeGroupFiles($dbFiles,$sizeGroup,$toBackupLocation);
        // print_r($dbFiles);
        $this->saveFile($this->dirApp['db'] . $nameBackup . '_Files.db',$dbFiles,$storageKeyCrypt);
        $this->saveFile($this->dirApp['db'] . $nameBackup . '_Backup.db',$dbBackup,$storageKeyCrypt);
    }
    
    function doUpdateDbFiles($idBackup,$dbFiles,$toBackupLocation)
    {
        foreach($dbFiles as $tFile=>$data)
        {
            $dbFiles[$tFile][$idBackup] = '-';
            krsort($dbFiles[$tFile]);
        }
        
        // ***********************************
        // ** Scan By LS lcoation to Backup **
        // ***********************************

        $filesList         = uniqid($this->dirApp['tmpLs'] . 'filesList_');
        $cmd = 'ls -AlRn --time-style="+%Y-%m-%d %H:%M:%S" ' . $toBackupLocation . ' > ' . $filesList;
        exec($cmd);
        echo $cmd . "\n";
        // $lsTime = microtime(true) - $startTime - $initTime;
        // echo 'Command LS time => ' . round($lsTime,5) . " sec\n";

        if (!is_file($filesList))
        {
            echo $this->borderBox('Error : file for => ' . $filesList,$this->styleBox);
            exit();
        }

        $ls = file($filesList);
        $newDir = true;
        $listFiles = false;

        // *********************
        // ** Update $dbFiles **
        // *********************

        $ls         = file($filesList);
        $newDir     = true;
        $listFiles  = false;

        // For each line from LS of $conf['toBackup']['location']
        foreach($ls as $line)
        {
            $line = trim($line);
            // Between to Dir in LS, there is a empty line
            if(!$line)
            {
                $newDir    = true;
                $listFiles = false;
                $nameDir   = null;
                continue;
            }
            // 1st line is the absolute path for the folder content below
            if ($newDir)
            {
                $checkLocation = explode($toBackupLocation,$line);
                if (empty($checkLocation[0]))
                {
                    $newDir = false;
                    $nameDir = trim(substr($checkLocation[1],0,-1),'/');
                }
                continue;
            }
            // 2nd line is the Total block for this folder, not usefull
            if (!$newDir and !$listFiles)
            {
                $partsTotalLine = explode(' ',$line);
                $listFiles      = ($partsTotalLine[0] == 'total');
                continue;
            }
            // until the empty line is the list of the content of the folder
            if (!$newDir and $listFiles)
            {
                $firstL = substr($line,0,1);
                if ($firstL == '-')
                {
                    preg_match_all($this->regLS, $line, $matches, PREG_SET_ORDER, 0);
                    
                    $right = isset($matches[0][1]) ? $matches[0][1] : false; // Right
                    $link  = isset($matches[0][2]) ? $matches[0][2] : false; // Link
                    $group = isset($matches[0][3]) ? $matches[0][3] : false; // Group
                    $user  = isset($matches[0][4]) ? $matches[0][4] : false; // User
                    $size  = isset($matches[0][5]) ? $matches[0][5] : false; // Size
                    $date  = isset($matches[0][6]) ? $matches[0][6] : false; // Date
                    $hours = isset($matches[0][7]) ? $matches[0][7] : false; // Hours
                    $file  = isset($matches[0][8]) ? $matches[0][8] : false; // File
                    // print_r($matches);
                    if (!$right or !$group or !$user or is_bool($size) or !$date or !$hours or !$file)
                    {
                        continue;
                    }
                    
                    $nameFile = ($nameDir ? $nameDir . '/' : '') . $file;
                    $dataFile = $right . '/' . $group . '-' . $user . '/' . $date . '-' . $hours . '/' . $size;
                    
                    if (!isset($dbFiles[$nameFile]))
                    {
                        $dbFiles[$nameFile][$idBackup] = $dataFile;
                    }
                    else
                    {
                        // Check is the file change between to backup
                        $alreadyBackup = false;
                        foreach($dbFiles[$nameFile] as $idBackupCheck => $stateFile)
                        {
                            if ($idBackup == $idBackupCheck or $stateFile == '=')
                            {
                                continue;
                            }
                            $alreadyBackup = ($stateFile == $dataFile);
                            break;
                        }
                        $dbFiles[$nameFile][$idBackup] = $alreadyBackup ? '=' : $dataFile;
                    }
                }
                elseif ($firstL == 'l')
                {
                    // Koi faire des liens
                    // Todo List
                }
                elseif ($firstL == 'd')
                {
                    // Si le Dir est vide
                    // Todo List
                }
            }
        }
        // print_r($dbFiles);
        return $dbFiles;
    }
    
    function makeGroupFiles($dbFiles,$sizeGroup,$toBackupLocation)
    {
        $listExtension['C1'] = 'exe,iso,img';
        $listExtension['C2'] = 'avi,mov,mpg,mpa,vob,mp3,mp4,jpeg,jpg,png,gif,tif,svg,bmp';
        $listExtension['C3'] = 'txt,html,htm,php,php5,php7,py,py2,py3,cvc,ini,js,bat,sh,htaccess,htpassword,eml,md,twgi,yml,yaml';
        foreach($listExtension as $idTypeExt=>$listExt)
        {
            foreach(explode(',',$listExt) as $ext)
            {
                $tableExtension[$ext] = $idTypeExt;
            }
        }
        // Manage the files removed and the folders size
        $filesToBackup = [];
        $idGroup = [];
        $size = [];
        $tmpDbFiles = $dbFiles;
        foreach($dbFiles as $nameFile=>$backupStateFile)
        {
            reset($backupStateFile);
            $idBackup = key($backupStateFile);
            $stateFile = current($backupStateFile);
            
            // If the file didn't change
            if ($stateFile == '=')
            {
                continue;
            }
            
            // If the file was remove
            if ($stateFile == '-')
            {
                continue;
            }
            
            // If the file change
            $infoFile  = pathinfo($nameFile);
            $dirName   = $infoFile['dirname'];
            $dirName   = $dirName == '.' ? '' : $dirName;
            $baseName  = $infoFile['basename'];
            $extension = isset($infoFile['extension']) ? $infoFile['extension'] : '';
            $groupExt  = isset($tableExtension[$extension]) ? $tableExtension[$extension] : 'C1' ;

            if (!isset($idGroup[$groupExt]) or !isset($size[$groupExt]) or $size[$groupExt] > $sizeGroup)
            {
                $idGroup[$groupExt] = uniqid($idBackup . '_',true);
                $size[$groupExt]    = 0;
            }
            $size[$groupExt] += basename($stateFile);
            
            $filesToBackup[$groupExt][$idGroup[$groupExt]][] = str_replace('/',$this->dirSep,$toBackupLocation . $nameFile);
            $tmpDbFiles[$nameFile][$idBackup] = $stateFile . '/' . $idGroup[$groupExt];
        }
        // print_r($filesToBackup);
        // print_r($tmpDbFiles);
        return [$filesToBackup,$tmpDbFiles];
    }
    
    function applyRetentionBackup($dbBackup,$retention)
    {
        if (empty($retention))
        {
            return $dbBackup;
        }
        
        reset($dbBackup);
        $lastStampTimeBackup = strtotime(current($dbBackup));
        
        $translation['M'] = 'minute';
        $translation['h'] = 'hours';
        $translation['d'] = 'day';
        $translation['w'] = 'week';
        $translation['m'] = 'month';
        $translation['y'] = 'year';

        $tabRetention = explode('-',$retention);
        $keepBackup = [];
        foreach($tabRetention as $n=>$rule)
        {
            list($periode,$interval) = explode('/',$rule);
            
            $nbr = substr($periode,0,-1);
            $letter = substr($periode,-1);
            $periodeStamp = time() - strtotime('-' . $nbr . ' ' . $translation[$letter]);
            
            $nbr = substr($interval,0,-1);
            $letter = substr($interval,-1);
            $intervalStamp = time() - strtotime('-' . $nbr . ' ' . $translation[$letter]);
            
            $nbrBackup = round($periodeStamp / $intervalStamp);
            
            $maxPeriode    = $lastStampTimeBackup - $periodeStamp;
            $startInterval = $lastStampTimeBackup - $intervalStamp;
            
            $c = 0;
            foreach($dbBackup as $idBackup=>$date)
            {
                $stamptime = strtotime($date);

                if (empty($keepBackup[$n]))
                {
                    $keepBackup[$n][$c] = $idBackup;
                    $c ++;
                    continue;
                }
                
                if ($startInterval < $stamptime)
                {
                    $keepBackup[$n][$c] = $idBackup;
                    continue;
                }
                
                $startInterval -= $intervalStamp;
                $c ++;
                
                if ($maxPeriode > $stamptime and $c > $nbrBackup)
                {
                    break;
                }
                
                $keepBackup[$n][$c] = $idBackup;
            }
        }
        
        foreach($keepBackup as $r=>$idBakcups)
        {
            foreach($idBakcups as $c=>$idBackup)
            {
                $newBackup[$idBackup] = $dbBackup[$idBackup];
            }
        }
        return $newBackup;
    }
    
    function runBackup($filesToBackup,$idBackup,$storageKeyCrypt,$storageLocation,$nameBackup)
    {
        $compressionType['C1'] = 'gzip -3';
        $compressionType['C2'] = 'gzip -7';
        $compressionType['C3'] = 'xz -9';
        $extensionType['C1'] = 'gz';
        $extensionType['C2'] = 'gz';
        $extensionType['C3'] = 'xz';
        foreach($filesToBackup as $type=>$groupFiles)
        {
            $compres   = isset($compressionType[$type]) ? $compressionType[$type] : 'gzip -3' ;
            $extension = isset($extensionType[$type])   ? $extensionType[$type]   : 'gz' ;
            foreach($groupFiles as $name=>$listFiles)
            {
                $listFilesForText      = implode("\n",$listFiles);
                $pathListFilesTobackup = uniqid($this->dirApp['tmpList'] . 'files_to_back_up_') . '.txt';
                file_put_contents($pathListFilesTobackup,$listFilesForText);
                $cmd = 'tar -I \'' . $compres . '\' -c -T ' . $pathListFilesTobackup . ' --same-owner -H posix -f ' . $this->dirApp['tmpBackup'] . $name . '.tar.' . $extension;
                echo $cmd . "\n";
                exec($cmd);
                $nameDirEncrypted = str_replace(['/','='],[',,','__'],openssl_encrypt(gzcompress($name . '.tar.' . $extension,9), "AES-256-ECB" ,$storageKeyCrypt));
                $dirEncryptedFile = implode('/',str_split($nameDirEncrypted,15));
                if (basename($dirEncryptedFile) < 5)
                {
                    $dirEncryptedFile = implode('/',str_split($nameDirEncrypted,10));
                }
                $tDir = dirname($dirEncryptedFile);
                $cmd  = 'mkdir -p ' . $storageLocation . $nameBackup . '/' . $tDir;
                exec($cmd);
                
                $cmd = 'openssl enc -aes-256-ecb -pbkdf2 -e -k ' . $storageKeyCrypt . ' -in ' . $this->dirApp['tmpBackup'] . $name . '.tar.' . $extension . ' -out ' . $storageLocation . $nameBackup . '/' . $dirEncryptedFile;
                $cmd = 'openssl enc -aes-256-ecb -e -k ' . $storageKeyCrypt . ' -in ' . $this->dirApp['tmpBackup'] . $name . '.tar.' . $extension . ' -out ' . $storageLocation . $nameBackup . '/' . $dirEncryptedFile;
                exec($cmd);
                echo $cmd . "\n";
            }
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    function loadFile($path,$storageKeyCrypt)
    {
        if (is_file($path))
        {
            return unserialize(openssl_decrypt(gzuncompress(file_get_contents($path)), 'AES-256-ECB' ,$storageKeyCrypt));
        }
        return null;
    }
    function saveFile($path,$data,$storageKeyCrypt)
    {
        return file_put_contents($path,gzcompress(openssl_encrypt(serialize($data), 'AES-256-ECB' ,$storageKeyCrypt),9));
    }
    function progressBar($variable, $max, $lenght=30)
    {
        static $startTime;

        if($variable > $max)
        {
            return;
        }
        if(empty($startTime))
        {
            $startTime = time();
        }
        $now = time();

        $remaining = $variable ? round(( ( $now - $startTime ) / $variable ) * ( $max - $variable ), 2) : 0;
        
        $elapsed = $now - $startTime;
        
        $percentage = (double) ( $variable / $max );
        $perc = number_format ($percentage * 100, 0);
        
        $bar = floor($percentage * $lenght);
        
        $statusBar  = "\r[";
        $statusBar .= str_repeat('=', $bar);
        if($bar >= $lenght)
        {
            $statusBar .= '=';
        }
        else
        {
            $statusBar .= ">";
            $statusBar .= str_repeat(' ', $lenght - $bar);
        }
        $statusBar .= '] ' . $perc . '%  ' . $variable . '/' . $max;
        $statusBar.= ' remaining: ' . number_format($remaining) . ' sec.  elapsed: ' . number_format($elapsed) . ' sec.';

        echo $statusBar;
        
        flush();
        
        if($variable == $max)
        {
            echo "\n";
        }
    }   
    function createFolder($path)
    {
        if (!is_dir($path))
        {
            mkdir($path,0755,true);
        }
        return is_dir($path);
    }
}

// class backup
// {
    
    // public function __construct($nameCode,$description,$keyParams,$styleBox=[])
    // {
        
    // }
// }

$nameCode    = 'Simply Backup';
$description = 'Make simply backup and restored';
$keyParams   = [
                '_newConf'    => ['keyWord' => 'newConf',
                                  'help'    => 'Make a list of the Vhost in this server'],
                '_makebackup' => ['keyWord' => 'makebackup',
                                  'help'    => 'Make the backup from the config folder'],
               ];
               
$styleBox['key-top']      = '-';
$styleBox['key-left']     = '|';
$styleBox['key-right']    = '|';
$styleBox['key-bottom']   = '-';

$phpcli = new simplyBackup($nameCode,$description,$keyParams,$styleBox);
$phpcli->doAction();

// $startTime = microtime(true);




// $initTime = microtime(true) - $startTime;
// echo 'Initialisation time => ' . round($initTime,5) . " sec\n";


// print_r($dbFiles);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";
// Manage Backup keeped period
    // Todo List


// print_r($filesInFolder);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";
// print_r($sizeDirectory);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";
// ************************************************
// ** Backup Strategy to reduce the time process **
// ************************************************

// Calcul the size for every level of directory
// $sizeSumDirectory = [];
// foreach($sizeDirectory as $path=>$size)
// {
    // $sumDri = '';
    // $path = $path ? '/' . $path : '';
    // foreach(explode('/',$path) as $dir)
    // {
        // $sumDri .= $dir;
        // if (empty($sizeSumDirectory[$sumDri]))
        // {
            // $sizeSumDirectory[$sumDri] = 0;
        // }
        // $sizeSumDirectory[$sumDri] += $size;
        // $sumDri .= $sumDri ? '/' : '';
    // }
// }
// print_r($sizeSumDirectory);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";

// Don't keep the folder too small
// $keepDir = [];
// foreach(array_reverse($sizeSumDirectory) as $path=>$size)
// {
    // $nbrLevel = count(explode('/',$path));
    // if ($size >= 50000000 or $nbrLevel < 2)
    // {
        // $keepDir[$path] = $size;
    // }
// }
// print_r($keepDir);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";

// Get the files for the keeped folder
// $dirToBackup = [];
// foreach($filesInFolder as $nameFile=>$baseName)
// {
    // if (isset($keepDir[$baseName]))
    // {
        // $dirToBackup[$baseName][] = $toBackupLocation . $nameFile;
        // continue;
    // }
    // $tableDir = explode('/',$baseName);
    // $nbrParts = count($tableDir);
    // foreach($tableDir as $n=>$data)
    // {
        // $nNow = $nbrParts - $n;
        // $dirNow = implode('/',array_slice($tableDir, 0, $nNow));
        // if (isset($dirToBackup[$dirNow]))
        // {
            // $dirToBackup[$dirNow][] = $toBackupLocation . $nameFile;
            // break;
        // }
    // }
// }

// saveFile($dirDb . $nameBackup . '_Files.db',$dbFiles,$storageKeyCrypt);
// print_r($dirToBackup);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";

// $compareTime = microtime(true) - $startTime - $initTime - $lsTime;
// echo 'Compare time => ' . round($compareTime,5) . " sec\n";
// if ($storageType == 'local')
// {
    // $totalFile = count($filesToBackup);
    // $nn = 0;
    // foreach($dirToBackup as $dir => $tableFiles)
    // {
        // $listFilesForText      = implode("\n",$tableFiles);
        // $pathListFilesTobackup = uniqid($dirTmpList . 'files_to_back_up_') . '.txt';
        // file_put_contents($pathListFilesTobackup,$listFilesForText);
        
        // $tNameFile       = uniqid($dirTmpBackup . 'tmp_');
        // $tNameFilePatern = $tNameFile . '-%04d.tar.gz';
        // $tNameFile      .= '-0001.tar.gz';
        // // $cmd = 'printf \'n ' . $tNameFilePatern . '\\n\' {2..9999} | tar -z -c -T ' . $pathListFilesTobackup . ' --same-owner -H posix -M -L 102400 -f ' . $tNameFile . '';
        // $cmd = 'tar -I \'xz -9\' -c -T ' . $pathListFilesTobackup . ' --same-owner -H posix -f ' . $tNameFile . '';
        // echo $cmd . "\n";
        // exec($cmd);
        
        // $listTarGz = array_slice(scandir($dirTmpBackup), 2);
        // foreach($listTarGz as $n => $fileTarGz)
        // {
            // $nameDirEncrypted = str_replace(['/','='],[',,','__'],openssl_encrypt(gzcompress($idBackup . '/' . $dir,9), "AES-256-ECB" ,$storageKeyCrypt));
            // $dirEncryptedFile = implode('/',str_split($nameDirEncrypted,15));
            // if (basename($dirEncryptedFile) < 3)
            // {
                // $dirEncryptedFile = implode('/',str_split($nameDirEncrypted,10));
            // }
            // $tDir = dirname($dirEncryptedFile);
            // $cmd  = 'mkdir -p ' . $storageLocation . $nameBackup . '/' . $tDir;
            // exec($cmd);
            // echo $cmd . "\n";
            
            // $cmd = 'openssl enc -aes-256-ecb -pbkdf2 -e -k ' . $storageKeyCrypt . ' -in ' . $dirTmpBackup . $fileTarGz . ' -out ' . $storageLocation . $nameBackup . '/' . $dirEncryptedFile . '+' . $n;
            // exec($cmd);
            // echo $cmd . "\n";
        // }
        // $cmd = 'rm -fr ' . $dirTmpBackup . '*';
        // exec($cmd);
        // echo $cmd . "\n";
    // }
// }
// $runTime = microtime(true) - $startTime - $initTime - $lsTime - $compareTime;
// echo 'Total time => ' . round($runTime,5) . " sec\n";

// $cmd = 'rm -fr ' . $dirTmp . '*';
// exec($cmd);

// to restore
// openssl enc -aes-256-ecb -pbkdf2 -d -k qsdlfkjIU6728Hol0HJKBJkjefe123JNCBdgsq -in <file> -out <out file>
// gzip -dc <out file> > <restore file>


// ******************* Error when it's Running *******************
// sh: -c: line 0: `mkdir -p /root/simplyback/tmp/backup/1/-rw-r--r--/1/5004/5005/0/clients/client2/web1/web/apps/files_external_gdrive/vendor/symfony/finder/Tests/Fixtures/r+e.gex[c]a(r)s/dir/'
// sh: -c: line 0: syntax error near unexpected token `('
// sh: -c: line 0: `ln -s /var/www/clients/client2/web1/web/apps/files_external_gdrive/vendor/symfony/finder/Tests/Fixtures/r+e.gex[c]a(r)s/dir/bar.dat /root/simplyback/tmp/backup/1/-rw-r--r--/1/5004/5005/0/clients/client2/web1/web/apps/files_external_gdrive/vendor/symfony/finder/Tests/Fixtures/r+e.gex[c]a(r)s/dir/bar.dat'*[=>                             ] 5%  24971/519116 remaining: 32,810 sec.  elapsed: 1,658 sec.ln: target 'space/foo.txt' is not a directory



        // foreach($tableFiles as $n => $file)
        // {
            // $addPath = $filesToBackup[$file];
            // $dirName  = dirname($file);
            // $baseName = basename($file);
            // $pathTemp = $docRoot . 'tmp/backup/' . $idBackup . '/' . $addPath . '/' . ($dirName == '.' ? '' : $dirName . '/');
            // $cmd = 'mkdir -p ' . $pathTemp;
            // exec($cmd);
            // echo $cmd . "\n";
            // $cmd = 'ln -s ' . $toBackup_location . $file . ' ' . $pathTemp . $baseName;
            // exec($cmd);
            // echo $cmd . "\n";
            // $nn ++;
            // progressBar($nn, $totalFile); 
        // }
        // $tNameFile = uniqid($docRoot . 'tmp/tmp_') . '.tar.gz';
        // $cmd = 'cd ' . $docRoot . 'tmp/backup/ && tar -hczf ' . $tNameFile . ' *';
        // exec($cmd);
        // echo $cmd . "\n";
        
        // $cmd = 'rm -fr ' . $docRoot . 'tmp/backup/*';
        // exec($cmd);
        // echo $cmd . "\n";
        // $tableNewLine = str_split($nameDirEncrypted,10);
        // $endName = $tableNewLine[count($tableNewLine)-1];
        // unset($tableNewLine[count($tableNewLine)-1]);
        // $tDir = implode('/', $tableNewLine);
        
        // $cmd = 'mkdir -p ' . $storage_location . $nameBackup . '/' . $tDir;
        // exec($cmd);
        // echo $cmd . "\n";
        // $cmd = 'openssl enc -aes-256-ecb -pbkdf2 -e -k ' . $storageKeyCrypt . ' -in ' . $tNameFile . ' -out ' . $storage_location . $nameBackup . ($tDir ? '/' : '') . $tDir . '/' . $endName;
        // exec($cmd);
        // echo $cmd . "\n";
        // $cmd = 'rm -f ' . $tNameFile;
        // exec($cmd);
        // echo $cmd . "\n";
        
        // break;
    // }
    // $cmd = 'cd ~';
    // exec($cmd);