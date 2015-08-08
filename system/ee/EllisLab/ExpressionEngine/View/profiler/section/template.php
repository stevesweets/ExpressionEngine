<?php foreach ($profiler_data as $label => $log): ?>
<div class="tab t-<?=$index?> <?=($index==0)?'tab-open':''?>">
	<div class="debug-content">
		<h2><?=lang('profiler_'.$label)?></h2>
		<?php if ( ! is_array($log)): ?>
			<p><?=$log?></p>
		<?php else: ?>
			<ul class="process-list">
				<?php foreach ($log as $i => $item): ?>
					<li><mark><?=$item['time']?> / <?=$item['memory']?></mark> </b><?=($item['message'])?></b>
					<?php if ($item['details']): ?>
						(<a class="toggle" rel="snp-detail-<?=$i?>" href="#">show more</a>)
						<div class="details snp-detail-<?=$i?>">
							<pre><?=$item['details']?></pre>
						</div>
					<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
<?php endforeach; ?>