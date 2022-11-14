<?php
namespace app\controller;

use app\BaseController;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;
use think\facade\Request;

class Index extends BaseController
{
    /**
     * @var \Telegram\Bot\Api
     */
    private Api $telegram;

    public function __construct(){
        $this->telegram = new Api('5735688118:AAHu3aWay5VEbwWDRCnZ9xQqMfNYHhU0zqg');
    }
    public function sendmassage(){
        $telegram = $this->telegram;
        $response = $telegram->getUpdates();
//        return $response;
        return $response;
    }
    public function storemassage(Request $request){
//        $request->validate([
//            'name'=>'required',
//            'massage'=>'required'
//        ]);
        var_dump(45465464);
        $text =  "<b>Name: </b>\n"
            . "$request->name\n"
            . "<b>Message: </b>\n"
            . $request->message;
        $result = Telegram::sendMessage([
            'chat_id' =>$request->chat_id?: '5655239373',
            'parse_mode' => 'HTML',
            'text' => $text
        ]);
        return $result;

    }
    public function storephoto(Request $request){
        $request->validate([
            'file' => 'file|mimes:jpeg,png,gif'
        ]);

        $photo = $request->file('file');

        Telegram::sendPhoto([
            'chat_id' => '5655239373',
            'photo' => InputFile::createFromContents(file_get_contents($photo->getRealPath()), str_random(10) . '.' . $photo->getClientOriginalExtension()),
            'caption' => 'Photo Image'
        ]);

        return redirect()->back();

    }
}
