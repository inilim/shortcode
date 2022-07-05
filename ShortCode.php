<?php

Class ShortCode
{
   static public ?ShortCode $instance;

   private string $dir = 'OFI';
   private string $prefix = 'OFI_';
   // колличество найденных шорткодов в контенте
   private ?int $count = null;
   private ?int $iteration = null;
   // уровень текущего вложенности
   private ?int $currentLevel = null;
   // последний уровень вложенности
   private bool $lastLevel = false;
   // текущий шорткод
   private ?string $currentShortCode = null;

   // массив для шорткодов
   private array $array = [];
   // массив для шорткодов которые отработают в самом конце
   private array $arrayLastWorkCurrentLevel = [];

   // запросы от шорткодов
   private array $requests = [];
   // информация по каждому шорткоду
   private array $info = [];
   // все найденные имена шорткодов
   private array $listFoundCode = [];
   // шорткоды которые должны быть удалены
   private array $forDeletion = [];
   // шорткоды которые были удалены
   private array $removed = [];
   // история добавленных шорткодов
   private array $historyQueue = [];
   // шорткоды которые были добавлены для последующего добавления в очередь
   private array $currentQueue = [];
   // шорткоды у которых не был найдена функция
   private array $undefined = [];
   // шорткоды у который значение присутствует управляющие символы ("][").
   private array $warnings = [];

   public function __construct ()
   {
      self::$instance = $this;
   }

   private function setLevel ()
   {
      $this->currentLevel = is_null($this->currentLevel) ? 1 : ($this->currentLevel+1);
   }

   /**
    * сохраняем найденные шорткоды
    *
    */
   private function setListFoundCode ():void
   {
      $this->listFoundCode[$this->currentLevel] = array_map(fn($a) => $a[1], $this->array);
   }
   /**
    * добавить шорткод в конец текущей очереди.
    * шорткод можно добавить один раз в текущую очередь.
    * 
    */
   public function addLastPosCurrentQueue (string $name, string $value = ''):bool
   {
      $this->checkCallPublicMethod();
      return $this->addPosCurrentQueue($name, $value, true);
   }
   /**
    * добавить шорткод сразу после текущего шорткода в текущей очереди
    * шорткод можно добавить один раз в текущую очередь.
    */
   public function addAfterPosCurrentQueue (string $name, string $value = ''):bool
   {
      $this->checkCallPublicMethod();
      return $this->addPosCurrentQueue($name, $value);
   }

   /**
    * добавить шорткод в текущую очередь.
    * шорткод можно добавить один раз в текущую очередь.
    */
   private function addPosCurrentQueue (string $name, string $value, bool $last = false):bool
   {
      $name = $this->getShortName($name);
      $this->checkCallMySelf($name);
      if(isset($this->historyQueue[$this->currentLevel][$name]) && $this->historyQueue[$this->currentLevel][$name])
      {
         return false;
      }
      $this->currentQueue[$name] = true;
      $this->setQueue(value: $value, last: $last);
      return true;
   }

   /**
    * добавляет запрос изнутри текущего функции шорткода.
    * "в разработке"
    */
   public function addRequest (string $request):bool
   {
      $this->checkCallPublicMethod();
      $hash = $this->hash($request);
      // предотвращаем повторные запросы
      if(isset($this->requests[$this->currentShortCode]) && $this->requests[$this->currentShortCode]['hash'] == $hash)
      {
         return false;
      }
      $this->requests[$this->currentShortCode] = [
         'request' => $request,
         'hash' => $hash,
      ];
      return true;
   }

   /**
    * удалить шорткод из текущей очереди.
    * 
    */
   public function removeShortCodeCurrentQueue (string $allName)
   {
      $this->checkCallPublicMethod();
   }

   /**
   * удалить шорткод из всех очередей.
   * 
   */
   public function removeShortCodeAllQueue (string $allName)
   {
      $this->checkCallPublicMethod();
   }


   private function hash (string $request): string
   {
      return sha1(json_encode([$this->currentShortCode, $request]), false);
   }
   
   /**
    * Проверить существование запроса от текущего шорткода. "в разработке"
    *
    */
   private function checkRequestCurrentShortCode (string $prefixName):bool
   {
      return isset($this->requests[$prefixName]);
   }

   /**
    * вернуть массив имен отработанных шорткодов
    *
    */
   public function getWorkedShortCodes (bool $prefixNone = false):array
   {
      $this->checkCallPublicMethod();
      $res = [];
      foreach($this->info[$this->currentLevel] as $prefixName => $list)
      {
         if($list['status_work'])
         {
            $res[] = $prefixNone ? $this->getShortName($prefixName) : $prefixName;
         }
      }
      return $res;
   }

   /**
    * Поставить шорткод в очередь.
    * Cразу после текущего шорткода или в конец
    * нужно переделать метод
    */
   private function setQueue (string $value, bool $last = false):void
   {
      $cnt = sizeof($this->currentQueue);
      if($cnt)
      {
         $this->count += $cnt;
         if($last)
         {
            foreach($this->currentQueue as $shortcode => $once)
            {
               $shortcode = $this->getShortName($shortcode);
               // записываем историю
               $this->historyQueue[$this->currentLevel][$shortcode] = $once;
               // шорткоды у которых был запрос не могут попасть в очередь
               if($this->checkRequestCurrentShortCode($this->getPrefixName($shortcode)))
               {
                  continue;
               }
               $this->array[] = [null, $shortcode, $value];
            }
         }
         else
         {
            foreach($this->currentQueue as $shortcode => $once)
            {
               $shortcode = $this->getShortName($shortcode);
               // записываем историю
               $this->historyQueue[$this->currentLevel][$shortcode] = $once;
               // шорткоды у которых был запрос не могут попасть в очередь
               if($this->checkRequestCurrentShortCode($this->getPrefixName($shortcode)))
               {
                  continue;
               }
               array_splice($this->array, ($this->iteration+1), 0, [[null, $shortcode, $value]]);
            }
         }
         // ресетим очередь
         $this->currentQueue = [];
      }
   }

   /**
    * вернуть шорткод c префиксом
    *
    */
   private function getPrefixName (string $allName):string
   {
      if(strpos($allName, $this->prefix) === 0)
      {
         return $allName;
      }
      return $this->prefix . $allName;
   }

   /**
    * вернуть шорткод без префикса
    *
    */
   private function getShortName (string $allName):string
   {
      return str_replace($this->prefix, '', $allName);
   }

   /**
    * выполнить запрос от шорткода
    * "в разработке"
    */
   private function executeRequest (string $prefixName):bool
   {
      if($this->requests[$prefixName]['request'] === 'lastWork')
      {
         $this->arrayLastWorkCurrentLevel[$prefixName] = $this->array[$this->iteration];
         // удаляем шорткод из текущей очереди
         unset( $this->array[$this->iteration] );
         // True если не нужно заменять шорткод
         return true;
      }
   }

   private function execFuncShortCode (string &$txt, string &$value)
   {
      $this->beforeWork($this->currentShortCode, $value);
      $resF = ($this->currentShortCode)($value, $txt);
      $this->afterWork($this->currentShortCode, gettype($resF));
      $resF = $resF ?? '';
      return $resF;
   }

   /**
    * выполняем шорткоды которые были перенесены в массив $this->arrayLastWorkCurrentLevel.
    * шорткоды в массиве $this->arrayLastWorkCurrentLevel выполняются в своем уровне.
    * "в разработке"
    */
   private function lastWorkCurrentLevel (string &$txt)
   {
      if(sizeof($this->arrayLastWorkCurrentLevel))
      {
         foreach($this->arrayLastWorkCurrentLevel as $function => $array)
         {
            $this->currentShortCode = $function;
            $resF = $this->execFuncShortCode($txt, $array[2]);

            // если функция возвращает отличное от типа зачений str и null, прерываем цикл
            if(!is_string($resF)) return $resF;

            $txt = $this->strReplaceOnce($array[0], $resF, $txt);
            unset( $this->arrayLastWorkCurrentLevel[$function] );
         }
      }
      return $txt;
   }

   /**
    * находит шорткоды
    *
    */
   private function matchShortCode (string &$txt):void
   {
      preg_match_all('#\[\[([a-zA-Z_0-9]{3,45})\#(.*?)\]\]#ms', $txt, $this->array, PREG_SET_ORDER);
      
   }

   /**
    * проверяем значение для шорткода на управляющие символы
    *
    */
   private function checkValueControlChar ():void
   {
      array_map(function($a){
         if (str_contains($a[2], '[') || str_contains($a[2], ']'))
         {
            $this->warnings[$this->getPrefixName($a[1])] = [
               '[' => substr_count($a[2], '['),
               ']' => substr_count($a[2], ']'),
            ];
         }
      }, $this->array);
   }

   /**
    * Проверяем признаки шорткода
    * 
    */
   private function signsShortCode (string &$txt):bool
   {
      return ($pos = strpos('-' . $txt, '[[')) && ($pos = strpos($txt, '#', $pos)) && strpos($txt, ']]', $pos);
   }

   private function work (string &$txt)
   {
      if($this->signsShortCode($txt))
      {
         $this->matchShortCode($txt);
         $this->checkValueControlChar();
         $this->count = sizeof($this->array);
         $this->setLevel();
         $this->setListFoundCode();

         /*
         $this->array[$this->iteration][0] - полный шорткод "[[name#value]]"
         $this->array[$this->iteration][1] - имя шорткода
         $this->array[$this->iteration][2] - значение шорткода
         */

         for($this->iteration = 0;
            $this->iteration < $this->count;
            $this->iteration++)
         {
            $function = $this->getPrefixName($this->array[$this->iteration][1]);
            $this->currentShortCode = $function;

            if($this->incF($function) && sizeof($this->array[$this->iteration]) === 3)
            {
               $resF = $this->execFuncShortCode($txt, $this->array[$this->iteration][2]);

               if($this->checkRequestCurrentShortCode($function) &&$this->executeRequest($function))
               {
                  continue;
               }

               // если функция возвращает отличное от типа зачений str и null, прерываем цикл
               if(!is_string($resF)) return $resF;

               $txt = $this->strReplaceOnce($this->array[$this->iteration][0], $resF, $txt);
            }
            else
            {
               # заменяем на пустоту если не найдена функция-шорткод.
               $txt = $this->strReplaceOnce($this->array[$this->iteration][0], '', $txt);
            }
            unset( $this->array[$this->iteration] );
         }
      }
      else
      {
         // здесь можно добавить обработку шорткодов для последнего уровня
         $this->lastLevel = true;
         return $this->lastWorkCurrentLevel($txt);
      }
      $txt = $this->lastWorkCurrentLevel($txt);

      if(!is_string($txt)) return $txt;

      return $this->work($txt);
   }

   public function run (string &$txt)
   {
      $txt = $this->work($txt);
      self::$instance = null;
      return $txt;
   }

   private function beforeWork (string $prefixName, string $value):void
   {
      $this->info[$this->currentLevel][$prefixName][] = [
         'value' => substr($value, 0, 50),
         'lenghtValue' => mb_strlen($value, 'UTF-8'),
         'statusWork' => false,
      ];
   }

   private function afterWork (string $prefixName, string $type_return):void
   {
      $key = array_keys($this->info[$this->currentLevel][$prefixName]);
      $key = end($key);
      $this->info[$this->currentLevel][$prefixName][$key]['statusWork'] = true;

      // проверяем был ли запрос от текущего шорткода
      if($this->checkRequestCurrentShortCode($prefixName))
      {
         $this->info[$this->currentLevel][$prefixName][$key]['request'] = true;
      }

      $this->info[$this->currentLevel][$prefixName][$key]['typeReturn'] = strtolower($type_return);
   }

   private function strReplaceOnce (?string $search, ?string $replace, string &$text):string
   {
      if(is_null($search)) return $text;
      $replace = $replace ?? '';
      $replace = $replace !== '' ? $this->wrapValue($replace) : '';
      $pos = strpos($text, $search);
      return $pos!==false ? substr_replace($text, $replace, $pos, strlen($search)) : $text;
   }

   private function wrapValue (string $value):string
   {
      $shortName = $this->getShortName($this->currentShortCode);

      return '<!--start ' . $shortName . ' ' . $this->currentLevel . ' lvl-->' .
         $value .
      '<!--end ' . $shortName . ' ' . $this->currentLevel . ' lvl-->';
   }

   /**
    * подключаем файл с функцией
    *
    */
   private function incF (string $prefixName):bool
   {
      if(!function_exists($prefixName))
      {
         if(is_file(__DIR__ . '/' . $this->dir . '/' . $prefixName . '.php'))
         {
            require_once __DIR__ . '/' . $this->dir . '/' . $prefixName . '.php';
            return function_exists($prefixName);
         }
      }
      else
      {
         return true;
      }
      $this->undefined[] = $prefixName;
      return false;
   }

   /**
    * некоторые публичные методы могут вызыватся только в функции-шорткода
    * Возможно есть адекватная альтернатива
    */
   private function checkCallPublicMethod ():void
   {
      $functions = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
      $functions = array_column($functions, 'function');
      if(!in_array($this->currentShortCode ?? '', $functions))
      {
         throw new Exception('ShortCode Exception: Method call can only be in a function-shortcode'); 
      }
   }

   /**
    * запрещаем класть в очередь шорткод из вызванного этого же шорткода
    *
    */
   private function checkCallMySelf (string $shortName):void
   {
      if($this->getShortName($this->currentShortCode) === $shortName)
      {
         throw new Exception('ShortCode Exception: You can\'t queue yourself');
      }
   }

   public function getAllInfo ():array
   {
      return [
         'listFoundCode' => $this->listFoundCode,
         'info' => $this->info,
         'delete' => $this->delete,
         'requests' => $this->requests,
         'undefined' => $this->undefined,
         'warnings' => $this->warnings,
         'countLevel' => $this->currentLevel,
         'historyQueue' => $this->historyQueue,
         // для отладки
         'array' => $this->array,
         'arrayLastWorkCurrentLevel' => $this->arrayLastWorkCurrentLevel,
         'currentQueue' => $this->currentQueue,
         'lastLevel' => $this->lastLevel,
         'count' => $this->count,
         'iteration' => $this->iteration,
         'currentShortCode' => $this->currentShortCode,
         'instance' => self::$instance,
      ];
   }
}