<?php
class Time
{
	private $ts = 0;

	public function getSeconds(): int
	{
		return $this->ts % 60;
	}

	public function getMinutes(): int
	{
		return ($this->ts / 60) % 60;
	}

	public function getHours(): int
	{
		return $this->ts / 3600;
	}

	public function setTime(int $hours, int $minutes, int $seconds = 0): void
	{
		$this->ts = $hours * 3600 + $minutes * 60 + $seconds;
  }

  public function __construct(int $hours, int $minutes, int $seconds = 0)
  {
    $this->setTime($hours, $minutes, $seconds);
  }

  public function __toString(): string
  {
    return sprintf("%02d:%02d:%02d", $this->getHours(), $this->getMinutes(), $this->getSeconds());
  }

  public static function fromStr(string $str): Time
  {
    if (!preg_match("/^(\d{1,2}):(\d{1,2})(:(\d{1,2}))?$/", $str, $matches)) {
      throw new Exception("Invalid time string");
    }
    return new Time(intval($matches[1]), intval($matches[2]), array_key_exists(4, $matches) ? intval($matches[4]) : 0);
  }
}

echo Time::fromStr("12:54:1");
