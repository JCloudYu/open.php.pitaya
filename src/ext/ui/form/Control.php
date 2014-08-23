<?php
/**
 * VWIMSIS - Control.php
 * Created by JCloudYu on 2013/09/15 13:45
 */
	using('kernel.basis.PBObject');

	final class Control extends PBObject
	{
		public static function SelectOptions($options = array())
		{
			$content = array();
			foreach ($options as $opt)
			{
				$opt['selected'] = empty($opt['selected']) ? '' : 'selected';
				$content[] = @"<option value='{$opt['value']}' title='{$opt['title']}' {$opt['selected']} >{$opt['label']}</option>";
			}

			return implode("\n", $content);
		}

		public static function Paging($curPage, $totalPages, $options = array())
		{
			$pageWrapperClass = @"{$options['class']['wrapper']}";
			$pageItemClass	  = @"{$options['class']['item']}";

			$urlTpl		= TO(@$options['page-url'], 'string');
			$rangeSize	= TO(@$options['page-range'], 'int');
			if (empty($rangeSize)) $rangeSize = 5;


			$baseLocale = array(
				'first page' => '',
				'last page'  => ''
			);

			if (is_array(@$options['label']))
			{
				$baseLocale['first page'] = @"{$options['label']['first page']}";
				$baseLocale['last page']  = @"{$options['label']['last page']}";
			}

			// INFO: Don't display if there are no pages
			if ($totalPages < 1) return '';


			// INFO: Prepare current range's buttons
			$totalSec	  = ceil((float)$totalPages / (float)$rangeSize);
			$curSec		  = ceil((float)$curPage / (float)$rangeSize);
			$leadingPage  = (($curSec - 1) * $rangeSize) + 1;
			$taillingPage = min($totalPages, $curSec * $rangeSize);
			$displayRange = range($leadingPage, $taillingPage, 1);

			$pageTplVal	  = array();
			foreach ($displayRange as $pageNum)
			{
				$pageTplVal[] =
					array(
						':page'	 => $pageNum,
						':url' 	 => strtr($urlTpl, array(':page' => $pageNum)),
						':ext'	 => ($pageNum == $curPage) ? "class='{$pageItemClass} active'" : "class='{$pageItemClass}'"
					);
			}
			$pageItems = ext_strtr("<li :ext><a href=':url'>:page</a></li>", $pageTplVal);



			if ($curSec > 1)
			{
				$prevSecPage = ($curSec - 1) * $rangeSize;
				$item = strtr("<li class='{$pageItemClass}'><a href=':url'>&hellip; {$prevSecPage}</a></li>", array(':url' => strtr($urlTpl, array(':page' => $prevSecPage))));
				array_unshift($pageItems, $item);


				if ($prevSecPage > 1)
				{
					$display = empty($baseLocale['first page']) ? 1 : $baseLocale['first page'];
					$item = strtr("<li class='{$pageItemClass}'><a href=':url'>{$display}</a></li>", array(':url' => strtr($urlTpl, array(':page' => 1))));
					array_unshift($pageItems, $item);
				}
			}






			if ($totalSec > $curSec)
			{
				$nextSecPage = min($totalPages, $curSec * $rangeSize + 1);
				$item = strtr("<li class='{$pageItemClass}'><a href=':url'>{$nextSecPage} &hellip;</a></li>", array(':url' => strtr($urlTpl, array(':page' => $nextSecPage))));
				array_push($pageItems, $item);


				if ($nextSecPage != $totalPages)
				{
					$display = empty($baseLocale['last page']) ? $totalPages : $baseLocale['last page'];
					$item = strtr("<li class='{$pageItemClass}'><a href=':url'>{$display}</a></li>", array(':url' => strtr($urlTpl, array(':page' => $totalPages))));
					array_push($pageItems, $item);
				}
			}


			$pageItems = implode('', $pageItems);
			return "<ul class='{$pageWrapperClass}'>{$pageItems}</ul>";
		}
	}
