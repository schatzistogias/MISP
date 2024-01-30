<?php
    echo $this->element('genericElements/assetLoader', [
        'js' => ['doT', 'moment.min'],
    ]);

    $URL_ADD = '/analystData/add/';
    $URL_EDIT = '/analystData/edit/';
    $URL_DELETE = '/analystData/delete/';

    $seed = isset($seed) ? $seed : mt_rand();

    $notes = !empty($notes) ? $notes : [];
    $opinions = !empty($opinions) ? $opinions : [];
    $relationships = !empty($relationships) ? $relationships : [];

    $related_objects = [
        'Attribute' => [],
        'Event' => [],
        'Object' => [],
        'Organisation' => [],
        'GalaxyCluster' => [],
        'Galaxy' => [],
        'Note' => [],
        'Opinion' => [],
        'SharingGroup' => [],
    ];
    foreach ($relationships as $relationship) {
        if (!empty($relationship['related_object'][$relationship['related_object_type']])) {
            $related_objects[$relationship['related_object_type']][$relationship['related_object_uuid']] = $relationship['related_object'][$relationship['related_object_type']];
        }
    }

    $notesOpinions = array_merge($notes, $opinions);
    $notesOpinionsRelationships = array_merge($notesOpinions, $relationships);
?>

<script>

if (!window.shortDist) {
    var shortDist = <?= json_encode($shortDist) ?>;
}
var renderedNotes<?= $seed ?> = null

function renderNote(note, relationship_related_object) {
    note.modified_relative = note.modified ? moment(note.modified).fromNow() : note.modified
    note.created_relative = note.created ? moment(note.created).fromNow() : note.created
    note.modified = note.modified ? (new Date(note.modified)).toLocaleString() : note.modified
    note.created = note.created ? (new Date(note.created)).toLocaleString() : note.created
    note.distribution_text = note.distribution != 4 ? shortDist[note.distribution] : note.SharingGroup.name
    note.distribution_color = note.distribution == 0 ? '#ff0000' : (note.distribution == 4 ? '#0088cc' : '#000')
    note.authors = Array.isArray(note.authors) ? note.authors.join(', ') : note.authors;
    note._permissions = {
        can_edit: true,
        can_delete: true,
        can_add: true,
    }
    if (note.note_type == 0) { // analyst note
        note.content = analystTemplate(note)
    } else if (note.note_type == 1) { // opinion
        note.opinion_color = note.opinion == 50 ? '#333' : ( note.opinion > 50 ? '#468847' : '#b94a48');
        note.opinion_text = (note.opinion  >= 81) ? '<?= __("Strongly Agree") ?>' : ((note.opinion  >= 61) ? '<?= __("Agree") ?>' : ((note.opinion  >= 41) ? '<?= __("Neutral") ?>' : ((note.opinion  >= 21) ? '<?= __("Disagree") ?>' : '<?= __("Strongly Disagree") ?>')))
        note.content = opinionTemplate(note)
    } else if (note.note_type == 2) {
        note.content = renderRelationshipEntryFromType(note, relationship_related_object)
    }
    var noteHtml = baseNoteTemplate(note)
    return noteHtml
}


function getURLFromRelationship(note) {
    if (note.related_object_type == 'Event') {
        return baseurl + '/events/view/' + note.related_object_uuid
    } else if (note.related_object_type == 'Attribute') {
        return baseurl + '/events/view/' + note.attribute.event_id + '/focus:' + note.related_object_uuid
    } else if (note.related_object_type == 'Object') {
        return baseurl + '/events/view/' + note.object.event_id + '/focus:' + note.related_object_uuid
    }
    return '#'
}

function renderRelationshipEntryFromType(note, relationship_related_object) {
    var contentHtml = ''
    var template = doT.template('\
        <span style="border: 1px solid #ddd !important; border-radius: 3px; padding: 0.25rem;"> \
            <span class="ellipsis-overflow" style="max-width: 12em;">{{=it.related_object_type}}</span> \
            :: \
            <span class="ellipsis-overflow" style="max-width: 12em;">{{=it.related_object_uuid}}</span> \
        </span> \
    ')
    var templateEvent = doT.template('\
            <span class="misp-element-wrapper attribute" title="<?= __('Event') ?>"> \
                <span class="bold"> \
                    <span class="attr-type"><span><i class="<?= $this->FontAwesome->getClass('envelope') ?>"></i></span></span> \
                    <span class=""><span class="attr-value"> \
                        <span class="ellipsis-overflow" style="max-width: 12em;"><a href="{{=it.urlEvent}}" target="_blank">{{=it.content}}</a></span> \
                    </span></span> \
                </span> \
            </span> \
        ')
    if (note.related_object_type == 'Event' && relationship_related_object.Event[note.related_object_uuid]) {
        note.event = relationship_related_object.Event[note.related_object_uuid]
        template = doT.template(templateEvent({content: '{{=it.event.info}}', urlEvent: '{{=it.url}}'}))
    } else if (note.related_object_type == 'Attribute' && relationship_related_object.Attribute[note.related_object_uuid]) {
        var event = templateEvent({content: '{{=it.attribute.Event.info}}', urlEvent: baseurl + '/events/view/{{=it.attribute.event_id}}'})
        note.attribute = relationship_related_object.Attribute[note.related_object_uuid]
        if (note.attribute.object_relation !== undefined && note.attribute.object_relation !== null) {
            template = doT.template('\
            ' + event + ' \
            <b>↦</b> \
            <span class="misp-element-wrapper object"> \
                <span class="bold"> \
                    <span class="obj-type"> \
                        <span class="object-name" title="<?= __('Object') ?>">{{=it.attribute.Object.name}}</span> \
                        ↦ <span class="object-attribute-type" title="<?= __('Object Relation') ?>">{{=it.attribute.object_relation}}</span> \
                    </span> \
                <span class="obj-value"><span class="ellipsis-overflow" style="max-width: 12em;"><a href="{{=it.url}}" target="_blank">{{=it.attribute.value}}</a></span></span> \
            </span> \
        ')
        } else if (relationship_related_object.Attribute[note.related_object_uuid]) {
            var event = templateEvent({content: '{{=it.attribute.Event.info}}', urlEvent: baseurl + '/events/view/{{=it.attribute.event_id}}'})
            template = doT.template('\
                ' + event + ' \
                <b>↦</b> \
                <span class="misp-element-wrapper attribute"> \
                    <span class="bold"> \
                        <span class="attr-type"><span title="<?= __('Attribute') ?>">{{=it.attribute.type}}</span></span> \
                        <span class="blue"><span class="attr-value"><span class="ellipsis-overflow" style="max-width: 12em;"><a href="{{=it.url}}" target="_blank">{{=it.attribute.value}}</a></span></span></span> \
                    </span> \
                </span> \
            ')
        }
    } else if (note.related_object_type == 'Object') {
        var event = templateEvent({content: '{{=it.object.Event.info}}', urlEvent: baseurl + '/events/view/{{=it.object.event_id}}'})
        note.object = relationship_related_object.Object[note.related_object_uuid]
        template = doT.template('\
            ' + event + ' \
            <b>↦</b> \
            <span class="misp-element-wrapper object"> \
                <span class="bold"> \
                    <span class="obj-type"> \
                        <i class="<?= $this->FontAwesome->getClass('cubes') ?>" title="<?= __('Object') ?>" style="margin: 0 0 0 0.25rem;"></i> \
                        <span>{{=it.object.name}}</span> \
                    </span> \
                    <span class="blue"><span class="obj-value"><span class="ellipsis-overflow" style="max-width: 12em;"><a href="{{=it.url}}" target="_blank">{{=it.object.id}}</a></span></span></span> \
                </span> \
            </span> \
        ')
    }
    note.url = getURLFromRelationship(note)
    contentHtml = template(note)
    return relationshipDefaultEntryTemplate({content: contentHtml, relationship_type: note.relationship_type, comment: note.comment})
}

var noteFilteringTemplate = '\
    <div class="btn-group notes-filtering-container" style="margin-bottom: 0.5rem"> \
        <btn class="btn btn-small btn-primary" href="#" onclick="filterNotes(this, \'all\')"><?= __('All notes') ?></btn> \
        <btn class="btn btn-small btn-inverse" href="#" onclick="filterNotes(this, \'org\')"><?= __('Organisation notes') ?></btn> \
        <btn class="btn btn-small btn-inverse" href="#" onclick="filterNotes(this, \'notorg\')"><?= __('Non-Org notes') ?></btn> \
    </div> \
'

var baseNoteTemplate = doT.template('\
    <div id="{{=it.note_type_name}}-{{=it.id}}" \
        class="analyst-note" \
        style="display: flex; flex-direction: row; align-items: center; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 1px 5px -2px rgb(0 0 0 / 0.5); border-radius: 0.25rem; padding: 0.25rem; margin-bottom: 0.0rem; background-color: #fff; transition: ease-out opacity 0.5s;" \
        data-org-uuid="{{=it.orgc_uuid}}" \
    > \
        <div style="flex-grow: 1;"> \
            <div style="display: flex; flex-direction: column;"> \
                <div style="display: flex; min-width: 250px; gap: 0.5rem;"> \
                    <img src="<?= $baseurl ?>/img/orgs/{{=it.Organisation.id}}.png" width="20" height="20" class="orgImg" style="width: 20px; height: 20px;" onerror="this.remove()" alt="Organisation logo"></object> \
                    <span style="margin-left: 0rem; margin-right: 0.5rem;"> \
                        <span>{{=it.Organisation.name}}</span> \
                        <i class="<?= $this->FontAwesome->getClass('angle-right') ?>" style="color: #999; margin: 0 0.25rem;"></i> \
                        <b>{{=it.authors}}</b> \
                    </span> \
                    <span style="display: inline-block; font-weight: lighter; color: #999">{{=it.modified_relative}} • {{=it.modified}}</span> \
                    <span style="margin-left: 0.5rem; flex-grow: 1; text-align: right; color: {{=it.distribution_color}}"> \
                        {{? it.distribution == 4 }} \
                            <a href="<?= $baseurl ?>/sharingGroups/view/{{=it.SharingGroup.id}}" target="_blank">{{=it.distribution_text}}</a> \
                        {{??}} \
                            {{=it.distribution_text}} \
                        {{?}} \
                    </span> \
                    <span class="action-button-container" style="margin-left: auto; display: flex; gap: 0.2rem;"> \
                        {{? it._permissions.can_add }} \
                            <span role="button" onclick="addOpinion(this, \'{{=it.uuid}}\', \'{{=it.note_type_name}}\')" title="<?= __('Add an opinion to this note') ?>"><i class="<?= $this->FontAwesome->getClass('gavel') ?> useCursorPointer"></i></span> \
                        {{?}} \
                        {{? it._permissions.can_add }} \
                        <span role="button" onclick="addNote(this, \'{{=it.uuid}}\', \'{{=it.note_type_name}}\')" title="<?= __('Add a note to this note') ?>"><i class="<?= $this->FontAwesome->getClass('comment-alt') ?> useCursorPointer"></i></span> \
                        {{?}} \
                        {{? it._permissions.can_edit }} \
                        <span role="button" onclick="editNote(this, {{=it.id}}, \'{{=it.note_type_name}}\')" title="<?= __('Edit this note') ?>"><i class="<?= $this->FontAwesome->getClass('edit') ?> useCursorPointer"></i></span> \
                        {{?}} \
                        {{? it._permissions.can_delete }} \
                        <span role="button" onclick="deleteNote(this, {{=it.id}})" title="<?= __('Delete this note') ?>" href="<?= $baseurl . $URL_DELETE ?>{{=it.note_type_name}}/{{=it.id}}"><i class="<?= $this->FontAwesome->getClass('trash') ?> useCursorPointer"></i></span> \
                        {{?}} \
                    </span> \
                </div> \
                <div style="">{{=it.content}}</div> \
            </div> \
        </div> \
    </div> \
')
var analystTemplate = doT.template('\
    <div style="max-width: 40vw; margin-top: 0.5rem; font-size:"> \
        {{=it.note}} \
    </div> \
')
var opinionGradient = '\
    <div class="opinion-gradient-container" style="width: 10rem; height: 6px;">\
        <span class="opinion-gradient-dot"></span> \
        <div class="opinion-gradient opinion-gradient-negative"></div> \
        <div class="opinion-gradient opinion-gradient-positive"></div> \
    </div> \
'
var opinionTemplate = doT.template('\
    <div style="margin: 0.75rem 0 0.25rem 0; display: flex; flex-direction: row;" title="<?= __('Opinion:') ?> {{=it.opinion}} /100"> \
        ' + opinionGradient + ' \
        <span style="line-height: 1em; margin-left: 0.25rem; margin-top: -3px;"> \
            <b style="margin-left: 0.5rem; color: {{=it.opinion_color}}">{{=it.opinion_text}}</b> \
            <b style="margin-left: 0.25rem; color: {{=it.opinion_color}}">{{=it.opinion}}</b> \
            <span style="font-size: 0.7em; font-weight: lighter; color: #999">/100</span> \
        </span> \
    </div> \
    {{? it.comment }} \
        <div style="max-width: 40vw; margin: 0.5rem 0 0 0.5rem; position: relative;" class="v-bar-text-opinion"> \
            {{=it.comment}} \
        </div> \
    {{?}} \
')
var relationshipDefaultEntryTemplate = doT.template('\
    <div style="max-width: 40vw; margin: 0.5rem 0 0.5rem 0.25rem;"> \
        <div style="display: flex; flex-direction: row; align-items: center; flex-wrap: nowrap;"> \
            <i class="<?= $this->FontAwesome->getClass('minus') ?>" style="font-size: 1.5em; color: #555"></i> \
            <span style="text-wrap: nowrap; padding: 0 0.25rem; border: 2px solid #555; border-radius: 0.25rem;">{{=it.relationship_type}}</span> \
            <i class="<?= $this->FontAwesome->getClass('long-arrow-alt-right') ?>" style="font-size: 1.5em; color: #555"></i> \
            <div style="margin-left: 0.5rem;">{{=it.content}}</div> \
        </div> \
        {{? it.comment }} \
            <div style="max-width: 40vw; margin: 0.5rem 0 0 0.5rem; position: relative;" class="v-bar-text-opinion"> \
                {{=it.comment}} \
            </div> \
        {{?}} \
    </div> \
')
var replyNoteTemplate = doT.template('\
    <span class="reply-to-note-collapse-button reply-to-group" onclick="$(this).toggleClass(\'collapsed\').next().toggle()" title="<?= __('Toggle annotation for this note') ?>" \
        style="width: 12px; height: 12px; border-radius: 50%; border: 1px solid #0035dc20; background: #ccccccdd; box-sizing: border-box; line-height: 12px; padding: 0 1px; cursor: pointer; margin: calc(-0.5rem - 6px) 0 calc(-0.5rem - 6px) -1px; z-index: 2;" \
    > \
        <i class="<?= $this->FontAwesome->getClass('angle-up') ?>" style="line-height: 8px;"></i> \
    </span> \
    <div class="reply-to-note reply-to-group" style="position: relative; display: flex; flex-direction: column; gap: 0.5rem; margin-left: 3px; border-left: 4px solid #ccccccaa; background: #0035dc10; padding: 0.5rem; border-radius: 5px; border-top-left-radius: 0;"> \
        {{=it.notes_html}} \
    </div> \
')

function filterNotes(clicked, filter) {
    $(clicked).closest('.notes-filtering-container').find('.btn').addClass('btn-inverse').removeClass('btn-primary')
    $(clicked).removeClass('btn-inverse').addClass('btn-primary')
    var $container = $(clicked).parent().parent().find('.all-notes')
    $container.find('.analyst-note').show()
    $container.find('.reply-to-group').show()
    $container.find('.analyst-note').filter(function() {
        var $note = $(this)
        // WEIRD. reply-to-group is not showing up!
        if (filter == 'all') {
            return false
        } else if (filter == 'org') {
            var shouldHide = $note.data('org-uuid') != '<?= $me['Organisation']['uuid'] ?>'
            if (shouldHide && $note.next().hasClass('reply-to-group')) { // Also hide reply to button and container
                $note.next().hide().next().hide()
            }
            return shouldHide
        } else if (filter == 'notorg') {
            var shouldHide = $note.data('org-uuid') == '<?= $me['Organisation']['uuid'] ?>'
            if (shouldHide && $note.next().hasClass('reply-to-group')) { // Also hide reply to button and container
                $note.next().hide().next().hide()
            }
            return shouldHide
        }
    }).hide()
}


(function() {
    var notes = <?= json_encode($notesOpinions) ?>;
    var relationships = <?= json_encode($relationships) ?>;
    var relationship_related_object = <?= json_encode($related_objects) ?>;
    var container_id = false
    <?php if (isset($container_id)): ?>
        container_id = '<?= h($container_id) ?>'
    <?php endif; ?>

    var nodeContainerTemplate = doT.template('\
        <div> \
            <ul class="nav nav-tabs" style="margin-bottom: 10px;"> \
                <li class="active"><a href="#notes-<?= $seed ?>" data-toggle="tab"><?= __('Notes & Opinions') ?></a></li> \
                <li><a href="#relationships-<?= $seed ?>" data-toggle="tab"><?= __('Relationships') ?></a></li> \
            </ul> \
            <div class="tab-content" style="padding: 0.25rem; max-width: 992px; min-width: 400px;"> \
                <div id="notes-<?= $seed ?>" class="tab-pane active"> \
                    ' + noteFilteringTemplate + ' \
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;" class="all-notes">{{=it.content_notes}}</div>\
                </div> \
                <div id="relationships-<?= $seed ?>" class="tab-pane"> \
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">{{=it.content_relationships}}</div>\
                </div> \
            </div> \
        </div> \
    ')

    function renderNotes(notes, relationship_related_object) {
        var renderedNotesArray = []
        if (notes.length == 0)  {
            var emptyHtml = '<span style="text-align: center; color: #777;"><?= __('No notes for this UUID.') ?></span>'
            renderedNotesArray.push(emptyHtml)
        } else {
            notes.forEach(function(note) {
                var noteHtml = renderNote(note, relationship_related_object)
    
                if (note.Opinion && note.Opinion.length > 0) { // The notes has more notes attached
                    noteHtml += replyNoteTemplate({notes_html: renderNotes(note.Opinion, relationship_related_object)})
                }
                if (note.Note && note.Note.length > 0) { // The notes has more notes attached
                    noteHtml += replyNoteTemplate({notes_html: renderNotes(note.Note, relationship_related_object)})
                }

                renderedNotesArray.push(noteHtml)
            });
        }
        return renderedNotesArray.join('')
    }

    function renderAllNotesWithForm(relationship_related_object) {
        var buttonContainer = '<div style="margin-top: 0.5rem">' + addNoteButton + addOpinionButton + '</div>'
        renderedNotes<?= $seed ?> = nodeContainerTemplate({
            content_notes: renderNotes(notes.filter(function(note) { return note.note_type != 2}), relationship_related_object) + buttonContainer,
            content_relationships: renderNotes(relationships, relationship_related_object) + addRelationshipButton,
        })
        if (container_id) {
            $('#' + container_id).html(renderedNotes<?= $seed ?>)
        }
    }

    var addNoteButton = '<button class="btn btn-small btn-block btn-primary" type="button" onclick="createNewNote(this, \'<?= $object_type ?>\', \'<?= $object_uuid ?>\')"> \
        <i class="<?= $this->FontAwesome->getClass('plus') ?>"></i> <?= __('Add a note') ?> \
    </button>'
    var addOpinionButton = '<button class="btn btn-small btn-block btn-primary" style="margin-top: 2px;" type="button" onclick="createNewOpinion(this, \'<?= $object_type ?>\', \'<?= $object_uuid ?>\')"> \
        <i class="<?= $this->FontAwesome->getClass('gavel') ?>"></i> <?= __('Add an opinion') ?> \
    </button>'
    var addRelationshipButton = '<button class="btn btn-small btn-block btn-primary" type="button" onclick="createNewRelationship(this, \'<?= $object_type ?>\', \'<?= $object_uuid ?>\')"> \
        <i class="<?= $this->FontAwesome->getClass('plus') ?>"></i> <?= __('Add a relationship') ?> \
    </button>'

    $(document).ready(function() {
        renderAllNotesWithForm(relationship_related_object)
    })
})()

    function createNewNote(clicked, object_type, object_uuid) {
        note_type = 'Note';
        openGenericModal(baseurl + '<?= $URL_ADD ?>' + note_type + '/' + object_uuid + '/' + object_type)
    }

    function createNewOpinion(clicked, object_type, object_uuid) {
        note_type = 'Opinion';
        openGenericModal(baseurl + '<?= $URL_ADD ?>' + note_type + '/' + object_uuid + '/' + object_type)
    }

    function createNewRelationship(clicked, object_type, object_uuid) {
        note_type = 'Relationship';
        openGenericModal(baseurl + '<?= $URL_ADD ?>' + note_type + '/' + object_uuid + '/' + object_type)
    }

    function addNote(clicked, note_uuid, object_type) {
        note_type = 'Note';
        openGenericModal(baseurl + '<?= $URL_ADD ?>' + note_type + '/' + note_uuid + '/' + object_type)
    }

    function addOpinion(clicked, note_uuid, object_type) {
        note_type = 'Opinion';
        openGenericModal(baseurl + '<?= $URL_ADD ?>' + note_type + '/' + note_uuid + '/' + object_type)
    }

    function editNote(clicked, note_id, note_type) {
        openGenericModal(baseurl + '<?= $URL_EDIT ?>' + note_type + '/' + note_id)
    }
    
    function deleteNote(clicked, note_id) {
        var deletionSuccessCallback = function(data) {
            $(clicked).closest('.analyst-note').remove()
        }
        popoverConfirm(clicked, '<?= __('Confirm deletion of this note') ?>', undefined, deletionSuccessCallback)
    }

    function replaceNoteInUI(data) {
        var noteType = Object.keys(data)[0]
        var noteHTMLID = '#' + data[noteType].note_type_name + '-' + data[noteType].id
        var $noteToReplace = $(noteHTMLID)
        if ($noteToReplace.length == 1) {
            var compiledUpdatedNote = renderNote(data[noteType])
            $noteToReplace[0].outerHTML = compiledUpdatedNote
            $(noteHTMLID).css({'opacity': 0})
            setTimeout(() => {
                $(noteHTMLID).css({'opacity': 1})
            }, 750);
        }
    }

</script>

<style>

    .action-button-container > span {
        visibility: hidden;
    }
    .analyst-note:hover .action-button-container > span {
        visibility: visible;
    }

    .reply-to-note-collapse-button.collapsed {
        margin-bottom: -0.25rem !important;
    }

    .v-bar-text-opinion::before {
        content: '';
        margin-right: 5px;
        margin-left: 2px;
        border-left: 1px solid;
        border-bottom: 1px solid;
        height: 1.3rem;
        width: 5px;
        display: inline-block;
        float: left;
        margin-top: -12px;
        border-color: #969696;
    }

    .reply-to-note-collapse-button.collapsed > i {
        transform: rotate(180deg);
    }

    .opinion-gradient-container {
        display: flex;
        position: relative;
        background: #ccc;
        border-radius: 3px;
    }
    .opinion-gradient {
        display: inline-block;
        position: relative;
        height: 100%;
        width: 50%;
    }
    .opinion-gradient-positive {
        border-radius: 0 3px 3px 0;
        background-image: linear-gradient(90deg, rgb(237, 212, 0), rgb(236, 211, 0), rgb(234, 211, 0), rgb(233, 210, 0), rgb(231, 210, 0), rgb(230, 209, 1), rgb(229, 209, 1), rgb(227, 208, 1), rgb(226, 208, 1), rgb(224, 207, 1), rgb(223, 207, 1), rgb(222, 206, 1), rgb(220, 206, 1), rgb(219, 205, 1), rgb(218, 204, 1), rgb(216, 204, 2), rgb(215, 203, 2), rgb(213, 203, 2), rgb(212, 202, 2), rgb(211, 202, 2), rgb(209, 201, 2), rgb(208, 201, 2), rgb(206, 200, 2), rgb(205, 200, 2), rgb(204, 199, 2), rgb(202, 199, 2), rgb(201, 198, 3), rgb(199, 197, 3), rgb(198, 197, 3), rgb(197, 196, 3), rgb(195, 196, 3), rgb(194, 195, 3), rgb(192, 195, 3), rgb(191, 194, 3), rgb(189, 194, 3), rgb(188, 193, 3), rgb(186, 193, 3), rgb(185, 192, 4), rgb(183, 192, 4), rgb(182, 191, 4), rgb(180, 190, 4), rgb(179, 190, 4), rgb(177, 189, 4), rgb(175, 189, 4), rgb(174, 188, 4), rgb(173, 188, 4), rgb(171, 187, 4), rgb(170, 186, 4), rgb(168, 186, 4), rgb(167, 185, 4), rgb(165, 185, 4), rgb(164, 184, 4), rgb(162, 183, 4), rgb(161, 183, 4), rgb(159, 182, 4), rgb(158, 182, 4), rgb(156, 181, 4), rgb(154, 180, 4), rgb(153, 180, 4), rgb(151, 179, 4), rgb(149, 179, 5), rgb(148, 178, 5), rgb(146, 178, 5), rgb(144, 177, 5), rgb(143, 177, 5), rgb(141, 176, 5), rgb(139, 175, 5), rgb(138, 175, 5), rgb(136, 174, 5), rgb(134, 173, 5), rgb(133, 173, 5), rgb(131, 172, 5), rgb(130, 172, 5), rgb(128, 171, 5), rgb(126, 170, 5), rgb(125, 170, 5), rgb(123, 169, 5), rgb(121, 168, 5), rgb(119, 168, 5), rgb(117, 167, 5), rgb(115, 167, 5), rgb(113, 166, 6), rgb(112, 165, 6), rgb(110, 165, 6), rgb(108, 164, 6), rgb(106, 163, 6), rgb(104, 163, 6), rgb(102, 162, 6), rgb(100, 162, 6), rgb(98, 161, 6), rgb(96, 160, 6), rgb(94, 159, 6), rgb(92, 159, 6), rgb(90, 158, 6), rgb(88, 157, 6), rgb(86, 157, 6), rgb(84, 156, 6), rgb(82, 155, 6), rgb(80, 155, 6),rgb(78, 154, 6))
    }
    .opinion-gradient-negative {
        border-radius: 3px 0 0 3px;
        background-image: linear-gradient(90deg, rgb(164, 0, 0), rgb(165, 8, 0), rgb(166, 15, 0), rgb(167, 21, 0), rgb(169, 25, 0), rgb(170, 30, 0), rgb(171, 33, 0), rgb(172, 37, 0), rgb(173, 40, 0), rgb(174, 43, 0), rgb(175, 46, 0), rgb(176, 49, 0), rgb(177, 52, 0), rgb(178, 55, 0), rgb(179, 57, 0), rgb(180, 60, 0), rgb(181, 63, 0), rgb(182, 65, 0), rgb(183, 68, 0), rgb(184, 70, 0), rgb(186, 72, 0), rgb(187, 75, 0), rgb(188, 77, 0), rgb(189, 80, 0), rgb(190, 82, 0), rgb(190, 84, 0), rgb(191, 86, 0), rgb(192, 88, 0), rgb(193, 90, 0), rgb(194, 92, 0), rgb(195, 95, 0), rgb(196, 96, 0), rgb(197, 98, 0), rgb(197, 100, 0), rgb(198, 102, 0), rgb(199, 104, 0), rgb(200, 106, 0), rgb(201, 108, 0), rgb(201, 110, 0), rgb(202, 112, 0), rgb(203, 114, 0), rgb(204, 116, 0), rgb(204, 118, 0), rgb(205, 119, 0), rgb(206, 121, 0), rgb(207, 123, 0), rgb(208, 125, 0), rgb(208, 127, 0), rgb(209, 128, 0), rgb(210, 130, 0), rgb(210, 132, 0), rgb(211, 134, 0), rgb(212, 135, 0), rgb(212, 137, 0), rgb(213, 139, 0), rgb(214, 141, 0), rgb(214, 143, 0), rgb(215, 144, 0), rgb(216, 146, 0), rgb(216, 148, 0), rgb(217, 149, 0), rgb(217, 151, 0), rgb(218, 153, 0), rgb(219, 154, 0), rgb(219, 156, 0), rgb(220, 158, 0), rgb(220, 160, 0), rgb(221, 161, 0), rgb(222, 163, 0), rgb(222, 164, 0), rgb(223, 166, 0), rgb(223, 168, 0), rgb(224, 169, 0), rgb(225, 171, 0), rgb(225, 173, 0), rgb(226, 174, 0), rgb(226, 176, 0), rgb(227, 178, 0), rgb(227, 179, 0), rgb(227, 181, 0), rgb(228, 182, 0), rgb(228, 184, 0), rgb(229, 186, 0), rgb(229, 187, 0), rgb(230, 189, 0), rgb(230, 190, 0), rgb(231, 192, 0), rgb(231, 193, 0), rgb(232, 195, 0), rgb(232, 196, 0), rgb(233, 198, 0), rgb(233, 200, 0), rgb(234, 201, 0), rgb(234, 203, 0), rgb(235, 204, 0), rgb(235, 206, 0), rgb(236, 207, 0), rgb(236, 209, 0), rgb(237, 210, 0), rgb(237, 212, 0));
    }
    .opinion-gradient-dot {
        width: 12px;
        height: 12px;
        position: absolute;
        top: -3px;
        z-index: 10;
        box-shadow: 0 0 2px 0px #00000066;
        border-radius: 50%;
        background-color: white;
    }


<?php
if(!function_exists("genStyleForOpinionNotes")) {
    function genStyleForOpinionNotes($notes) {
        foreach ($notes as $note) {
            genStyleForOpinionNote($note);
            if (!empty($note['Note'])) {
                genStyleForOpinionNotes($note['Note']);
            }
            if (!empty($note['Opinion'])) {
                genStyleForOpinionNotes($note['Opinion']);
            }
        }
    }
}
if(!function_exists("genStyleForOpinionNote")) {
    function genStyleForOpinionNote($note) {
        if ($note['note_type'] != 1) { // opinion
            return;
        }
        $opinion_color_scale_100 = ['rgb(164, 0, 0)', 'rgb(166, 15, 0)', 'rgb(169, 25, 0)', 'rgb(171, 33, 0)', 'rgb(173, 40, 0)', 'rgb(175, 46, 0)', 'rgb(177, 52, 0)', 'rgb(179, 57, 0)', 'rgb(181, 63, 0)', 'rgb(183, 68, 0)', 'rgb(186, 72, 0)', 'rgb(188, 77, 0)', 'rgb(190, 82, 0)', 'rgb(191, 86, 0)', 'rgb(193, 90, 0)', 'rgb(195, 95, 0)', 'rgb(197, 98, 0)', 'rgb(198, 102, 0)', 'rgb(200, 106, 0)', 'rgb(201, 110, 0)', 'rgb(203, 114, 0)', 'rgb(204, 118, 0)', 'rgb(206, 121, 0)', 'rgb(208, 125, 0)', 'rgb(209, 128, 0)', 'rgb(210, 132, 0)', 'rgb(212, 135, 0)', 'rgb(213, 139, 0)', 'rgb(214, 143, 0)', 'rgb(216, 146, 0)', 'rgb(217, 149, 0)', 'rgb(218, 153, 0)', 'rgb(219, 156, 0)', 'rgb(220, 160, 0)', 'rgb(222, 163, 0)', 'rgb(223, 166, 0)', 'rgb(224, 169, 0)', 'rgb(225, 173, 0)', 'rgb(226, 176, 0)', 'rgb(227, 179, 0)', 'rgb(228, 182, 0)', 'rgb(229, 186, 0)', 'rgb(230, 189, 0)', 'rgb(231, 192, 0)', 'rgb(232, 195, 0)', 'rgb(233, 198, 0)', 'rgb(234, 201, 0)', 'rgb(235, 204, 0)', 'rgb(236, 207, 0)', 'rgb(237, 210, 0)', 'rgb(237, 212, 0)', 'rgb(234, 211, 0)', 'rgb(231, 210, 0)', 'rgb(229, 209, 1)', 'rgb(226, 208, 1)', 'rgb(223, 207, 1)', 'rgb(220, 206, 1)', 'rgb(218, 204, 1)', 'rgb(215, 203, 2)', 'rgb(212, 202, 2)', 'rgb(209, 201, 2)', 'rgb(206, 200, 2)', 'rgb(204, 199, 2)', 'rgb(201, 198, 3)', 'rgb(198, 197, 3)', 'rgb(195, 196, 3)', 'rgb(192, 195, 3)', 'rgb(189, 194, 3)', 'rgb(186, 193, 3)', 'rgb(183, 192, 4)', 'rgb(180, 190, 4)', 'rgb(177, 189, 4)', 'rgb(174, 188, 4)', 'rgb(171, 187, 4)', 'rgb(168, 186, 4)', 'rgb(165, 185, 4)', 'rgb(162, 183, 4)', 'rgb(159, 182, 4)', 'rgb(156, 181, 4)', 'rgb(153, 180, 4)', 'rgb(149, 179, 5)', 'rgb(146, 178, 5)', 'rgb(143, 177, 5)', 'rgb(139, 175, 5)', 'rgb(136, 174, 5)', 'rgb(133, 173, 5)', 'rgb(130, 172, 5)', 'rgb(126, 170, 5)', 'rgb(123, 169, 5)', 'rgb(119, 168, 5)', 'rgb(115, 167, 5)', 'rgb(112, 165, 6)', 'rgb(108, 164, 6)', 'rgb(104, 163, 6)', 'rgb(100, 162, 6)', 'rgb(96, 160, 6)', 'rgb(92, 159, 6)', 'rgb(88, 157, 6)', 'rgb(84, 156, 6)', 'rgb(80, 155, 6)', 'rgb(78, 154, 6)'];
        $opinion = min(100, max(0, intval($note['opinion'])));
        ?>

        #Opinion-<?= $note['id'] ?> .opinion-gradient-<?= $opinion >= 50 ? 'negative' : 'positive' ?> {
            opacity: 0;
        }
        #Opinion-<?= $note['id'] ?> .opinion-gradient-dot {
            left: calc(<?= $opinion ?>% - 6px);
            background-color: <?= $opinion == 50 ? '#555' : $opinion_color_scale_100[$opinion] ?>;
        }
        <?php if ($opinion >= 50): ?>
            #Opinion-<?= $note['id'] ?> .opinion-gradient-positive {
                -webkit-mask-image: linear-gradient(90deg, black 0 <?= abs(-50 + $opinion)*2 ?>%, transparent <?= abs(-50 + $opinion)*2 ?>% 100%);
                mask-image: linear-gradient(90deg, black 0 <?= abs(-50 + $opinion)*2 ?>%, transparent <?= abs(-50 + $opinion)*2 ?>% 100%);
            }
        <?php else: ?>
            #Opinion-<?= $note['id'] ?> .opinion-gradient-negative {
                -webkit-mask-image: linear-gradient(90deg, transparent 0 <?= 100-(abs(-50 + $opinion)*2) ?>%, black <?= 100-(abs(-50 + $opinion)*2) ?>% 100%);
                mask-image: linear-gradient(90deg, transparent 0 <?= 100-(abs(-50 + $opinion)*2) ?>%, black <?= 100-(abs(-50 + $opinion)*2) ?>% 100%);
            }
        <?php endif; ?>

        <?php
    }
}

genStyleForOpinionNotes($notesOpinionsRelationships)
?>

</style>

<style>
    span.misp-element-wrapper {
        margin: 3px 3px;
        border: 1px solid #ddd !important;
        border-radius: 3px;
        white-space: nowrap;
        display: inline-block;
        padding: 0;
    }
    .misp-element-wrapper.attribute .attr-type {
        background-color: #f5f5f5 !important;
        border-right: 1px solid #ddd !important;
        display: inline-block;
    }
    .misp-element-wrapper.attribute .attr-type > span {
        margin: 2px 3px;
    }
    .misp-element-wrapper.attribute .attr-value {
        display: inline-table;
        margin: 0px 3px;
    }
    .misp-element-wrapper.attribute .attr-value > span {
        max-width: 300px;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        display: table-cell;
    }
    span.misp-element-wrapper.object {
        border: 1px solid #3465a4 !important;
    }
    .misp-element-wrapper.object .obj-type {
        display: inline-block;
        background-color: #3465a4 !important;
        color: #ffffff !important;
    }
    .misp-element-wrapper.object .obj-type .object-attribute-type {
        margin-left: 0;
        background-color: #f5f5f5;
        color: black;
        padding: 1px 3px;
        border-radius: 7px;
    }
    .misp-element-wrapper.object .obj-type > span {
        margin: 2px 3px;
    }
    .misp-element-wrapper.object .obj-value {
        display: inline-table;
        margin: 0px 3px;
    }
    .misp-element-wrapper.object .obj-value > span {
        max-width: 300px;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        display: table-cell;
    }
</style>