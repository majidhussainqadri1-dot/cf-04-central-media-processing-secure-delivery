<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class WorkspaceUploadService {
    public static function authorize(string $type,array $input,int $actor): array {
        Utils::requireRuntime();
        if($actor<1) throw new Error('workspace_login_required','Authenticated actor required.',401);
        $context=self::normalizeContext($type,$input);
        if($context['native_owner']===''||$context['native_object_id']==='') throw new Error('workspace_owner_context_required','Native workspace owner and object are required.',400);
        $decision=Auth::domainDecision($context['native_owner'],'authorize_upload',['actor_id'=>$actor,'workspace'=>$context,'operation'=>'workspace_authorize']);
        $context['owner_object_version']=(int)$decision['object_version'];
        return $context;
    }
    public static function normalizeContext(string $type,array $input): array {
        $type=Utils::key($type,16);
        if(!in_array($type,['image','audio'],true)) throw new Error('workspace_media_type_invalid','Unsupported workspace media type.',400);
        $base=['type'=>$type,'source'=>Utils::key((string)($input['source']??'upload'),32),'title'=>Utils::text((string)($input['title']??''),160),'caption'=>Utils::text((string)($input['caption']??''),1000),'native_owner'=>Utils::key((string)($input['native_owner']??''),64),'native_object_id'=>Utils::text((string)($input['native_object_id']??''),191),'strip_location_metadata'=>true];
        if($type==='image'){
            $rotation=(int)($input['rotation_degrees']??0);
            if(!in_array($rotation,[0,90,180,270],true)) throw new Error('image_rotation_invalid','Image rotation must be 0, 90, 180 or 270.',400);
            $quality=(int)($input['compression_quality']??82);
            if($quality<40||$quality>95) throw new Error('image_compression_quality_invalid','Image compression quality is outside policy.',400);
            $base+=['rotation_degrees'=>$rotation,'compression_quality'=>$quality,'alt_text'=>Utils::text((string)($input['alt_text']??''),400),'crop_requested'=>Utils::bool($input['crop_requested']??false)];
            if($base['crop_requested']){
                $crop=['x'=>(int)($input['crop_x']??0),'y'=>(int)($input['crop_y']??0),'width'=>(int)($input['crop_width']??0),'height'=>(int)($input['crop_height']??0)];
                if($crop['x']<0||$crop['y']<0||$crop['width']<1||$crop['height']<1) throw new Error('image_crop_invalid','Invalid crop rectangle.',400);
                $base['crop']=$crop;
            }
        } else {
            $base+=['waveform_required'=>true,'transcript_requested'=>Utils::bool($input['transcript_requested']??false),'captions_requested'=>Utils::bool($input['captions_requested']??false),'language'=>Utils::key((string)($input['language']??'und'),16)];
        }
        return $base;
    }
}
