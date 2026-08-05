<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$version='1.1.0-rc.3';
$plugin=$root.'/sabri-central-media';
$dist=$root.'/dist';
if(!is_dir($plugin)) throw new RuntimeException('plugin source missing');
if(!is_dir($dist) && !mkdir($dist,0775,true) && !is_dir($dist)) throw new RuntimeException('dist directory unavailable');
$tmp=sys_get_temp_dir().'/cf04-package-'.getmypid().'-'.bin2hex(random_bytes(4));
$target=$tmp.'/sabri-central-media';
$remove=static function(string $path) use (&$remove): void { if(!file_exists($path))return;if(is_dir($path)&&!is_link($path)){foreach(scandir($path)?:[] as $item){if($item==='.'||$item==='..')continue;$remove($path.'/'.$item);}@rmdir($path);}else{@unlink($path);} };
try {
    if(!mkdir($target,0775,true) && !is_dir($target)) throw new RuntimeException('temporary package directory unavailable');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as $file){
        $relative=substr($file->getPathname(),strlen($plugin)+1);
        $destination=$target.'/'.$relative;
        if($file->isDir()){if(!is_dir($destination)&&!mkdir($destination,0775,true)&&!is_dir($destination))throw new RuntimeException('package directory copy failed');continue;}
        if(!$file->isFile()||$file->isLink())throw new RuntimeException('unsupported package source entry: '.$relative);
        if(!is_dir(dirname($destination))&&!mkdir(dirname($destination),0775,true)&&!is_dir(dirname($destination)))throw new RuntimeException('package parent directory failed');
        if(!copy($file->getPathname(),$destination))throw new RuntimeException('package file copy failed: '.$relative);
        touch($destination,946684800);
        chmod($destination,0644);
    }
    $zip=$dist.'/cf-04-sabri-central-media-'.$version.'.zip';
    @unlink($zip);
    $cwd=getcwd();
    chdir($tmp);
    exec("LC_ALL=C find sabri-central-media -type f -print0 | LC_ALL=C sort -z | xargs -0 zip -X -q ".escapeshellarg($zip),$output,$code);
    chdir($cwd);
    if($code!==0||!is_file($zip)||filesize($zip)<1)throw new RuntimeException('package build failed');
    $files=[];
    $sourceIterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin,FilesystemIterator::SKIP_DOTS));
    foreach($sourceIterator as $file){
        if(!$file->isFile()||$file->isLink())continue;
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($plugin)+1));
        $files[]=['path'=>$relative,'sha256'=>hash_file('sha256',$file->getPathname()),'size'=>$file->getSize()];
    }
    usort($files,fn(array $a,array $b): int=>strcmp($a['path'],$b['path']));
    $manifest=['module'=>'CF-04','package_folder'=>'sabri-central-media','version'=>$version,'schema_version'=>'1.3.1','contract_version'=>'1.3.1','runtime_default'=>'disabled','build_epoch'=>'2000-01-01T00:00:00Z','files'=>$files];
    file_put_contents($dist.'/MANIFEST.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n",LOCK_EX);
    $sbom=['bomFormat'=>'CycloneDX','specVersion'=>'1.5','serialNumber'=>'urn:uuid:'.substr(hash('sha256','CF-04|'.$version),0,8).'-'.substr(hash('sha256','CF-04|'.$version),8,4).'-4'.substr(hash('sha256','CF-04|'.$version),13,3).'-a'.substr(hash('sha256','CF-04|'.$version),17,3).'-'.substr(hash('sha256','CF-04|'.$version),20,12),'version'=>1,'metadata'=>['timestamp'=>'2000-01-01T00:00:00Z','component'=>['type'=>'application','name'=>'CF-04 Sabri Central Media','version'=>$version,'properties'=>[['name'=>'runtime-default','value'=>'disabled'],['name'=>'wordpress-plugin-folder','value'=>'sabri-central-media']]]],'components'=>[]];
    file_put_contents($dist.'/SBOM.json',json_encode($sbom,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n",LOCK_EX);
    $checks=[basename($zip)=>hash_file('sha256',$zip),'MANIFEST.json'=>hash_file('sha256',$dist.'/MANIFEST.json'),'SBOM.json'=>hash_file('sha256',$dist.'/SBOM.json')];
    $checksumText='';foreach($checks as $name=>$hash)$checksumText.=$hash.'  '.$name."\n";
    file_put_contents($dist.'/CHECKSUMS.sha256',$checksumText,LOCK_EX);
    echo $zip,"\n";
} finally { $remove($tmp); }
