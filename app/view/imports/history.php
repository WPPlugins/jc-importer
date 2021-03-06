<?php
/**
 * @global JC_Importer $jcimporter
 */
global $jcimporter;

// load settings from gloabl
$importer_id   = $jcimporter->importer->get_ID();
$template_name = $jcimporter->importer->get_template_name();
$name          = $jcimporter->importer->get_name();

$columns = apply_filters( "jci/log_{$template_name}_columns", array() );
?>

<div id="icon-tools" class="icon32"><br></div>
<h2 class="nav-tab-wrapper">
	<a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=edit"
	   class="nav-tab tab"><?php echo $name; ?></a>
	<a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=history" class="nav-tab nav-tab-active tab">History</a>
</h2>

<div id="ajaxResponse"></div>


<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">


		<div id="post-body-content">

			<?php
			$log = isset( $_GET['log'] ) && intval( $_GET['log'] ) > 0 ? intval( $_GET['log'] ) : false;
			?>
			<?php if ( ! $log ): ?>

				<?php
				$rows = ImportLog::get_importer_logs( $importer_id );
				?>

				<div id="postbox-container-2" class="postbox-container">

					<h1>Import History</h1>

					<p>Click on a record below to view</p>

					<div id="jci-table-wrapper">
						<table class="wp-list-table widefat fixed posts" cellspacing="0">
							<thead>
							<tr>
								<th scope="col" id="author" class="manage-column column-author" style="width:30px;">ID
								</th>
								<th>Type</th>
								<th>Rows</th>
								<th>Date</th>
								<th>_</th>
							</tr>
							</thead>
							<tbody>
							<?php if ( $rows ): ?>
								<?php foreach ( $rows as $row ): ?>
									<tr>
										<td><?php echo $row->version; ?></td>
										<td><?php echo $row->type; ?></td>
										<td><?php echo $row->row_total; ?></td>
										<td><?php echo date( 'd/m/y', strtotime( $row->created ) ); ?></td>
										<td>
											<a href="admin.php?page=jci-importers&import=<?php echo $importer_id; ?>&log=<?php echo $row->version; ?>&action=history">View</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td colspan="5">No Records have been imported</td>
								</tr>
							<?php endif; ?>
							</tbody>
						</table>
					</div>

				</div>

			<?php else: ?>

				<div id="postbox-container-2" class="postbox-container">

					<h1>Import #<?php echo $log; ?> History</h1>

					<p><a href="admin.php?page=jci-importers&import=<?php echo $importer_id; ?>&action=history">&larr;
							Back to importer history</a></p>

					<?php
					$rows = ImportLog::get_importer_log( $importer_id, $log );
					?>

					<div id="jci-table-wrapper">
						<table class="wp-list-table widefat fixed posts" cellspacing="0">
							<thead>
							<tr>
								<th scope="col" id="author" class="manage-column column-author" style="width:30px;">ID
								</th>
								<?php foreach ( $columns as $key => $col ): ?>
									<th scope="col" id="<?php echo $key; ?>"
									    class="manage-column column-<?php echo $key; ?>"
									    style=""><?php echo $col; ?></th>
								<?php endforeach; ?>
							</tr>
							</thead>
							<tbody>
							<?php if ( $rows ): ?>
								<?php foreach ( $rows as $r ): ?>
									<?php
									$row  = $r->row;
									$data = array( unserialize( $r->value ) );
									require $jcimporter->get_plugin_dir() . 'app/view/imports/log/log_table_record.php';
									?>
								<?php endforeach; ?>
							<?php else: ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>

				</div>

			<?php endif; ?>

		</div>

		<div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>

		</div>
		<!-- /postbox-container-1 -->

	</div>
</div>