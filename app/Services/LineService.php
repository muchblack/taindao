<?php
namespace App\Services;

use Faker\Provider\ar_EG\Text;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Constants\HTTPHeader;
use LINE\Parser\EventRequestParser;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;
use LINE\Clients\MessagingApi\Configuration;
use App\Services\QiGuaService;
class LineService
{
    private $_bot;
    private $_qigua;

    public function __construct()
    {
        $channelToken = config('line.channel_access_token');
        $config = new Configuration();
        $config->setAccessToken($channelToken);
        $this->_bot = new MessagingApiApi(new \GuzzleHttp\Client(), $config);
        $this->_qigua = new QiGuaService();
    }

    public function webhook($request)
    {
        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
        $parsedEvents = EventRequestParser::parseEventRequest($request->getContent(), config('line.channel_secret'), $signature);
        foreach($parsedEvents->getEvents() as $event)
        {
            if(!($event instanceof  MessageEvent))
            {
                continue;
            }

            $message = $event->getMessage();
            if(!($message instanceof TextMessageContent))
            {
                continue;
            }

            $replyText = $message->getText();

            if($replyText == '起卦')
            {
                $guaMessages = $this->_guaFormat();
                $this->_bot->replyMessage( new ReplyMessageRequest([
                    'replyToken' => $event->getReplyToken(),
                    'messages' => $guaMessages
                ]));
            }
            else
            {
                $this->_bot->replyMessage( new ReplyMessageRequest([
                   'replyToken' => $event->getReplyToken(),
                   'messages' => [
                       (new TextMessage(['text' => '我不清楚你的問題']))->setType('text')
                   ]
                ]));
            }
        }

        return response('ok');
    }

    private function _guaFormat()
    {
        $gua = $this->_qigua->qiGua();

        $oriGua = "[本卦]  ".$gua['oriGua']['combine']."\n";
        $oriGua .= "[古解]\n".$gua['oriGua']['short']."\n";
        $oriGua .= "[運勢]\n".$gua['oriGua']['yun']."\n";
        $oriGua .= "[現解]\n".$gua['oriGua']['desc']."\n";

        $messages[] = (new TextMessage(['text'=> $oriGua]))->setType('text');

        if(isset($gua['chGua']))
        {
            $chGua = "[變卦]  ".$gua['chGua']['combine']."\n";
            $chGua .= "[古解]\n".$gua['chGua']['short']."\n";
            $chGua .= "[運勢]\n".$gua['chGua']['yun']."\n";
            $chGua .= "[現解]\n".$gua['chGua']['desc']."\n";

            $messages[] = (new TextMessage(['text'=>$chGua]))->setType('text');
        }

        return $messages;
    }
}
