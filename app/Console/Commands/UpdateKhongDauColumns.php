<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Char;

class UpdateKhongDauColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chars:update-khongdau';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật word_khongdau và meaning_khongdau cho bảng chars';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Bắt đầu cập nhật...');
        $chars = Char::all();
        foreach ($chars as $char) {
            $char->word_khongdau = $this->stripVietnameseAccents($char->word);
            $char->meaning_khongdau = $this->stripVietnameseAccents($char->meaning);
            $char->save();
        }
        $this->info('Đã cập nhật xong!');
    }

    private function stripVietnameseAccents($str)
    {
        $accents_arr = [
            'a'=>'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd'=>'đ',
            'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i'=>'í|ì|ỉ|ĩ|ị',
            'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
            'A'=>'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D'=>'Đ',
            'E'=>'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I'=>'Í|Ì|Ỉ|Ĩ|Ị',
            'O'=>'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U'=>'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y'=>'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
        ];
        foreach($accents_arr as $nonAccent=>$accent){
            $str = preg_replace("/($accent)/i", $nonAccent, $str);
        }
        // Thay dấu ngắt câu thành dấu cách
        $str = preg_replace('/[.,;:!?()\[\]{}"\'\-_…\/\\\|<>]/u', ' ', $str);
        // Gộp nhiều dấu cách liên tiếp thành 1 dấu cách
        $str = preg_replace('/\s+/u', ' ', $str);
        // Loại bỏ dấu cách đầu câu, thêm dấu cách vào cuối câu
        $str = ltrim($str) . ' ';
        return $str;
    }
}
