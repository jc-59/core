<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
?>
<script type="text/javascript" src="3rdparty/vivagraph/vivagraph.min.js"></script>
<style>
	#div_graphLinkRenderer > svg {
		height: 100%;
		width: 100%
	}
</style>
<div id="div_graphLinkRenderer" style="height:100%;width: 100%;"></div>
<script>
	var graph = Viva.Graph.graph();
	jeedom.getGraphData({
		filter_type : '<?php echo init('filter_type', '') ?>',
		filter_id : '<?php echo init('filter_id', '') ?>',
		success : function(data){
			for(var i in data.scenario){
				var scenario = data.scenario[i];
				graph.addNode('scenario'+scenario.id, {
					name : scenario.name,
					color : 'green',
					shape : 'rect',
					width : 10,
					height : 10
				});
			}


			for(var i in data.object){
				var object = data.object[i];
				graph.addNode('object'+object.id, {
					name : object.name,
					type : 'object',
					color : 'red',
					shape : 'rect',
					width : 10,
					height : 10
				});
				for(var j in object.eqLogic){
					var eqLogic = object.eqLogic[j];
					graph.addNode('eqLogic'+eqLogic.id, {
						name : eqLogic.name,
						type : 'eqLogic',
						color : 'blue',
						shape : 'rect',
						width : 10,
						height : 10
					});
					for(var l in eqLogic.cmd){
						var cmd = eqLogic.cmd[l];
						graph.addNode('cmd'+cmd.id, {
							name : cmd.name,
							type : 'cmd',
							color : 'black',
							shape : 'rect',
							width : 10,
							height : 10
						});
					}
				}
			}
			for(var i in data.object){
				var object = data.object[i];
				for(var j in object.eqLogic){
					var eqLogic = object.eqLogic[j];
					graph.addLink('object'+object.id, 'eqLogic'+eqLogic.id,{lengthfactor: 1});
					for(var l in eqLogic.cmd){
						var cmd = eqLogic.cmd[l];
						graph.addLink('eqLogic'+eqLogic.id, 'cmd'+cmd.id,{lengthfactor: 1});
						for(m in cmd.usedBy.eqLogic){
							var uEqLogic = cmd.usedBy.eqLogic[m];
							graph.addLink('eqLogic'+uEqLogic.id, 'cmd'+cmd.id,{lengthfactor: 1});
						}
						for(m in cmd.usedBy.cmd){
							var uCmd = cmd.usedBy.cmd[m];
							graph.addLink('cmd'+uCmd.id, 'cmd'+cmd.id,{lengthfactor: 1});
						}
						for(m in cmd.usedBy.scenario){
							var uScenario = cmd.usedBy.scenario[m];
							graph.addLink('scenario'+uScenario.id, 'cmd'+cmd.id,{lengthfactor: 1});
						}
					}
				}
			}
			render();
		}
	});

	function render(){
		var hWindow = $(window).outerHeight() - $('header').outerHeight() - $('#div_alert').outerHeight();
		$('#div_graphLinkRenderer').height(hWindow).css('overflow-y', 'auto').css('overflow-x', 'hidden').css('padding-top','5px');
		var graphics = Viva.Graph.View.svgGraphics();
		highlightRelatedNodes = function (nodeId, isOn) {
			graph.forEachLinkedNode(nodeId, function (node, link) {
				var linkUI = graphics.getLinkUI(link.id);
				if (linkUI) {
					linkUI.attr('stroke', isOn ? '#FF0000' : '#B7B7B7');
				}
			});
		};
		graphics.node(function(node) {
			if (typeof node.data == 'undefined') {
				graph.removeNode(node.id);
				return;
			}
			var ui = Viva.Graph.svg('g');
			svgText = Viva.Graph.svg('text').attr('y', '0px').text(node.data.name);
			img = Viva.Graph.svg(node.data.shape)
			.attr("width", node.data.width)
			.attr("height", node.data.height)
			.attr("fill", node.data.color);
			ui.append(svgText);
			ui.append(img);
			$(ui).hover(function () {
				highlightRelatedNodes(node.id, true);
			}, function () {
				highlightRelatedNodes(node.id, false);
			});
			return ui;
		}).placeNode(function (nodeUI, pos) {
			nodeUI.attr('transform',
				'translate(' +
				(pos.x - 24 / 3) + ',' + (pos.y - 24 / 2.5) +
				')');
		});

		var layout = Viva.Graph.Layout.forceDirected(graph, {
			springLength: 200,
			stableThreshold: 0.9,
			dragCoeff: 0.01,
			springCoeff: 0.0004,
			gravity: -20,
			springTransform: function (link, spring) {
				spring.length = 200 * (1 - link.data.lengthfactor);
			}
		});
		graphics.link(function (link) {
			dashvalue = '5, 0';
			if (link.data.isdash == 1) {
				dashvalue = '5, 2';
			}
			return Viva.Graph.svg('line').attr('stroke', '#B7B7B7').attr('stroke-dasharray', dashvalue).attr('stroke-width', '0.4px');
		});
		var renderer = Viva.Graph.View.renderer(graph, {
			layout: layout,
			graphics: graphics,
			prerender: 10,
			renderLinks: true,
			container: document.getElementById('div_graphLinkRenderer')
		});
		renderer.run();
		setTimeout(function () {
			renderer.pause();
			renderer.reset();
		}, 2000);
	}
</script>
