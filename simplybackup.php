<?php
// include 'functions.php';
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
        $cmd = 'mkdir -p ' . $path;
        exec($cmd);
    }
    return is_dir($path);
}

//            Right        Link         Group        User         Size         Date         Hour        File
$regLS = '/^(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(\S{1,}) {1,}(.*)$/m';

$startTime = microtime(true);

// include 'config.php';
$config['test'] = [
        'toBackup' => [
            'location' => '/var/www/clients/client1/web5/web/'
        ],
        'storage' => [
            'type'=>'local',
            'location' => '/var/backup/',
            'keyCrypt' => 'qsdlfkjIU6728Hol0HJKBJkjefe123JNCBdgsq',
            'compress' => 9,
        ],
    ];
    

// Charger_base_dossiers
$dbDirectory = [];
// Charger_base_fichiers

// Charger_base_dossier_distant
$dbDirectoryDistant = [];
foreach($dbDirectoryDistant as $tDir=>$data)
{
    $dbDirectoryDistant[$tDir] = '-';
}

$docRoot      = __DIR__  . '/';
$dirDb        = $docRoot . 'db/';
$dirTmp       = $docRoot . 'tmp/';
$dirTmpBackup = $dirTmp  . 'backup/';
$dirTmpLs     = $dirTmp  . 'ls/';
$dirTmpList   = $dirTmp  . 'list/';

createFolder($dirDb);
createFolder($dirTmp);
createFolder($dirTmpBackup);
createFolder($dirTmpLs);
createFolder($dirTmpList);

$nameBackup = 'test';
$conf = $config[$nameBackup];
$toBackupLocation  = $conf['toBackup']['location'];
$storageType       = $conf['storage'] ['type'];
$storageLocation   = $conf['storage'] ['location'];
$storageKeyCrypt   = $conf['storage'] ['keyCrypt'];
$filesList         = uniqid($dirTmpLs . 'filesList_');
$filesToBackup = [];
$dbFiles = loadFile($docRoot . 'db/' . $nameBackup . '_Files.db',$storageKeyCrypt);
if (!is_array($dbFiles))
{
    $dbFiles = [];
}
$dbBackup = loadFile($docRoot . 'db/' . $nameBackup . '_Backup.db',$storageKeyCrypt);
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

foreach($dbFiles as $tFile=>$data)
{
    $dbFiles[$tFile][$idBackup] = '-';
    krsort($dbFiles[$tFile]);
}
$initTime = microtime(true) - $startTime;
echo 'Initialisation time => ' . round($initTime,5) . " sec\n";

// ***********************************
// ** Scan By LS lcoation to Backup **
// ***********************************

$cmd = 'ls -AlRn --time-style="+%Y-%m-%d %H:%M:%S" ' . $toBackupLocation . ' > ' . $filesList;
exec($cmd);

$lsTime = microtime(true) - $startTime - $initTime;
echo 'Command LS time => ' . round($lsTime,5) . " sec\n";

if (!is_file($filesList))
{
    echo 'Error : file for => ' . $filesList;
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
            preg_match_all($regLS, $line, $matches, PREG_SET_ORDER, 0);
            $right = isset($matches[0][1]) ? $matches[0][1] : false; // Right
            $link  = isset($matches[0][2]) ? $matches[0][2] : false; // Link
            $group = isset($matches[0][3]) ? $matches[0][3] : false; // Group
            $user  = isset($matches[0][4]) ? $matches[0][4] : false; // User
            $size  = isset($matches[0][5]) ? $matches[0][5] : false; // Size
            $date  = isset($matches[0][6]) ? $matches[0][6] : false; // Date
            $hours = isset($matches[0][7]) ? $matches[0][7] : false; // Hours
            $file  = isset($matches[0][8]) ? $matches[0][8] : false; // File
            // print_r($matches);
            // if (!$right or !$group or !$user or !is_bool($size) or !$date or !$hours or !$file)
            // {
                // continue;
            // }
            
            $nameFile = ($nameDir ? $nameDir . '/' : '') . $file;
            $dataFile = $right . '/' . $group . '-' . $user . '/' . $date . '-' . $hours . '/' . $size;
            
            if (!isset($dbFiles[$nameFile]))
            {
                $dbFiles[$nameFile][$idBackup] = $dataFile;
                // $filesToBackup[$nameFile]      = $newLine;
                // $fileInDir[$nameFile]          = $nameDir;
                // $dbDirectory[$nameDir] += $size;
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
                
                // if (!$alreadyBackup)
                // {
                    // $filesToBackup[$nameFile] = dirname($newLine); // remove size for backup info
                    // $fileInDir[$nameFile] = $nameDir;
                    // $dbDirectory[$nameDir] += $size;
                // }
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
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";
// Manage Backup keeped period
    // Todo List

// Manage the files removed and the folders size
$filesToBackup = [];
$sizeDirectory = [];
$filesInFolder = [];
foreach($dbFiles as $nameFile=>$backupStateFile)
{
    reset($backupStateFile);
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
    $dirName  = dirname($nameFile);
    $dirName  = $dirName == '.' ? '' : $dirName;
    $baseName = basename($nameFile);
    $filesToBackup[$dirName][$baseName] = $stateFile;
    $filesInFolder[$nameFile] = $dirName;
    
    // Calcul size of folder with only the changed files
    if (!isset($sizeDirectory[$dirName]))
    {
        $sizeDirectory[$dirName] = 0;
    }
    $sizeDirectory[$dirName] += basename($stateFile);
}
// print_r($filesInFolder);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";
// print_r($sizeDirectory);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";
// ************************************************
// ** Backup Strategy to reduce the time process **
// ************************************************

// Calcul the size for every level of directory
$sizeSumDirectory = [];
foreach($sizeDirectory as $path=>$size)
{
    $sumDri = '';
    $path = $path ? '/' . $path : '';
    foreach(explode('/',$path) as $dir)
    {
        $sumDri .= $dir;
        if (empty($sizeSumDirectory[$sumDri]))
        {
            $sizeSumDirectory[$sumDri] = 0;
        }
        $sizeSumDirectory[$sumDri] += $size;
        $sumDri .= $sumDri ? '/' : '';
    }
}
// print_r($sizeSumDirectory);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";

// Don't keep the folder too small
$keepDir = [];
foreach(array_reverse($sizeSumDirectory) as $path=>$size)
{
    $nbrLevel = count(explode('/',$path));
    if ($size >= 50000000 or $nbrLevel < 2)
    {
        $keepDir[$path] = $size;
    }
}
// print_r($keepDir);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";

// Get the files for the keeped folder
$dirToBackup = [];
foreach($filesInFolder as $nameFile=>$baseName)
{
    if (isset($keepDir[$baseName]))
    {
        $dirToBackup[$baseName][] = $toBackupLocation . $nameFile;
        continue;
    }
    $tableDir = explode('/',$baseName);
    $nbrParts = count($tableDir);
    foreach($tableDir as $n=>$data)
    {
        $nNow = $nbrParts - $n;
        $dirNow = implode('/',array_slice($tableDir, 0, $nNow));
        if (isset($dirToBackup[$dirNow]))
        {
            $dirToBackup[$dirNow][] = $toBackupLocation . $nameFile;
            break;
        }
    }
}

// saveFile($dirDb . $nameBackup . '_Files.db',$dbFiles,$storageKeyCrypt);
// print_r($dirToBackup);
// echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";echo "\n";

$compareTime = microtime(true) - $startTime - $initTime - $lsTime;
echo 'Compare time => ' . round($compareTime,5) . " sec\n";
if ($storageType == 'local')
{
    $totalFile = count($filesToBackup);
    $nn = 0;
    foreach($dirToBackup as $dir => $tableFiles)
    {
        $listFilesForText      = implode("\n",$tableFiles);
        $pathListFilesTobackup = uniqid($dirTmpList . 'files_to_back_up_') . '.txt';
        file_put_contents($pathListFilesTobackup,$listFilesForText);
        
        $tNameFile       = uniqid($dirTmpBackup . 'tmp_');
        $tNameFilePatern = $tNameFile . '-%04d.tar.gz';
        $tNameFile      .= '-0001.tar.gz';
        // $cmd = 'printf \'n ' . $tNameFilePatern . '\\n\' {2..9999} | tar -z -c -T ' . $pathListFilesTobackup . ' --same-owner -H posix -M -L 102400 -f ' . $tNameFile . '';
        $cmd = 'tar -I \'xz -9\' -c -T ' . $pathListFilesTobackup . ' --same-owner -H posix -f ' . $tNameFile . '';
        echo $cmd . "\n";
        exec($cmd);
        
        $listTarGz = array_slice(scandir($dirTmpBackup), 2);
        foreach($listTarGz as $n => $fileTarGz)
        {
            $nameDirEncrypted = str_replace(['/','='],[',,','__'],openssl_encrypt(gzcompress($idBackup . '/' . $dir,9), "AES-256-ECB" ,$storageKeyCrypt));
            $dirEncryptedFile = implode('/',str_split($nameDirEncrypted,15));
            if (basename($dirEncryptedFile) < 3)
            {
                $dirEncryptedFile = implode('/',str_split($nameDirEncrypted,10));
            }
            $tDir = dirname($dirEncryptedFile);
            $cmd  = 'mkdir -p ' . $storageLocation . $nameBackup . '/' . $tDir;
            exec($cmd);
            echo $cmd . "\n";
            
            $cmd = 'openssl enc -aes-256-ecb -pbkdf2 -e -k ' . $storageKeyCrypt . ' -in ' . $dirTmpBackup . $fileTarGz . ' -out ' . $storageLocation . $nameBackup . '/' . $dirEncryptedFile . '+' . $n;
            exec($cmd);
            echo $cmd . "\n";
        }
        $cmd = 'rm -fr ' . $dirTmpBackup . '*';
        exec($cmd);
        echo $cmd . "\n";
    }
}
$runTime = microtime(true) - $startTime - $initTime - $lsTime - $compareTime;
echo 'Total time => ' . round($runTime,5) . " sec\n";

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