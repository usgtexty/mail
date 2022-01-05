<style>
	.oauth-text {
		text-align: center;
		font-size:  25px;
		padding: 20px;
		flex: auto;
	}
</style>

<p class="oauth-text">
	<?php if ($authed['status'] === 'error') : ?>
		<?php echo $authed['message']; ?> <a href="<?php echo $authed['url']; ?>">Back</a>
	<?php else: ?>
		 <?php echo $authed['message']; ?> <a href="<?php echo $authed['url']; ?>">View Mail</a>
	<?php endif; ?>
</p>
