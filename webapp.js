var thisPage = location.search.replace(/.*page=([^?&]+).*/i,'$1');
function systemWait(){
    if(document.readyState !== 'complete'){
        setTimeout('systemWait();', 10);
        return;
    }
    fixLinks(); // External links get new window.
    fixTextAreas();
//    fixHeight('section_2'); // Inflate ID'd element to fill viewable height.
    
    // csshover3.htc MUST be physically located in the absolute root of the domain.
    if( 'behavior' in document.body.style ){
        document.body.style.behavior = 'url(/csshover3.htc)';
    }
}
systemWait();

function showTab(tab){
    tabElems = document.getElementById('tab_buttons').childNodes;
    tabButtons = new Array();
    
    i = tabElems.length;
    while(i--){
        if(tabElems[i].nodeType !== 1){ continue; }
        tabElems[i].className = '';
    }
    document.getElementById('tab_button_'+tab).className = 'active';
    
    groupTabs = document.getElementById('group_tabs').childNodes;
    i = groupTabs.length;
    while(i--){ groupTabs[i].className = ''; }
    document.getElementById('group_tab_'+tab).className = 'active';
    
    fixTextAreas();
//    fixHeight('section_3');
}

function fixHeight(elemId){ // Updated 2013-03-01.
    // IE's clientHeight is ZERO until the minHeight prop is set.
    // FF's scrollHeight increases w/ minHeight, IE's doesn't.
    // Use clientHeight after fixing for IE.
    var windowHeight = document.documentElement.clientHeight; // Window height.
    var contentHeight = document.body.scrollHeight; // Content height.
    if(contentHeight >= windowHeight || !document.getElementById(elemId)){ return; }
    var heightDiff = windowHeight - contentHeight - 1; // Corrects for IE.
    var targetElem = document.getElementById(elemId).firstChild;
    targetElem.style.minHeight = '0'; // Corrects for IE.
    var currentHeight = targetElem.clientHeight;
    i=4;while(i--){
        currentHeight -= Number(
            getStyle(
                targetElem
                ,Array('marginTop','marginBottom','paddingTop','paddingBottom')[i]
            ).replace(/[^\d]*/g,'')
        );
    }
    targetElem.style.minHeight = currentHeight + heightDiff + 'px';
}

function fixElemSize(elem){
    if(elem.clientHeight < elem.scrollHeight){
        elem.style.height = elem.scrollHeight+10+'px';
    }
}
function fixTextAreas(){
    i=document.getElementsByTagName('textarea').length;
    while(i--){ fixElemSize( document.getElementsByTagName('textarea')[i] ); }
}

function showDebug(debugInfo){
    if(typeof debugInfo === 'undefined'){ debugInfo = ''; }
    else{ debugInfo += '<br/>'; }
    if( !document.getElementById('debugBox') ){
        var debugBox = document.createElement('div');
        debugBox.id = 'debugBox';
        with(debugBox.style){
            position = 'fixed';
            left = '50%';
            top = '0';
            backgroundColor = 'black';
            color = 'white';
        }
        document.body.appendChild(debugBox);
    }else{
        var debugBox = document.getElementById('debugBox');
    }
    debugBox.innerHTML = debugInfo+'HTML w:'+document.documentElement.clientWidth+' h:'+document.documentElement.clientHeight+'<br/>';
    debugBox.innerHTML += 'BODY w:'+document.body.clientWidth+' h:'+document.body.clientHeight+'<br/>';
    setTimeout('showDebug();',100);
}


//// Generic components good for all websites (steal this stuff, you know you want it).

function fixLinks(){
// External links get new window.
// Active links get different style.
// Updated: 2013-10-30-1305

    var eachLink, linkClass, matchArgs, i, ii, iii, thisOne;
    var location = window.location;
    if(location.pathname.match(/index\.php/i)){ // Redirect w/o filename.
        window.location = location.href.replace(/index\.php[^\?&\/]?/i,'');
    }
    // Flatten paths and searches(query).
    location.pathFixed = location.pathname.replace(/^\/*/ig,'/').replace(/\/*$/ig,'');
    location.args = location.search.replace(/^\?/i,'').split('&');
    i=document.links.length; while(i--){ // Walk links.
        eachLink = document.links[i];
        eachLink.pathFixed = eachLink.pathname.replace(/^\/*/ig,'/').replace(/\/*$/ig,'');
        // External links.
        if( eachLink.getAttributeNode('class') ){
            linkClass = ''+eachLink.getAttributeNode('class').value;
                 // Why not className, for nodes altered by scripting?
        }else{ linkClass = ''; }
        if( // Determine externals.
            linkClass.match(/(^|\s+)extlink(\s+|$)/i) || // Forced external.
            (
                !linkClass.match(/(^|\s+)intlink(\s+|$)/i) && // NOT forced internal.
                (
                    eachLink.hostname !== location.hostname || // Diff host.
                    eachLink.pathFixed !== location.pathFixed // Diff path.
                )
            )
        ){
            eachLink.onclick = function(){ window.open(this.href); return false; };
        }
        
        // Active links get diff style.
        if(
            eachLink.protocol === location.protocol
            && eachLink.hostname === location.hostname
            && eachLink.pathFixed === location.pathFixed
        ){
            if(eachLink.search || location.search){
                matchArgs = 0;
                eachLink.args = eachLink.search.replace(/^\?/i,'').split('&');
                ii=0;while(ii < eachLink.args.length){
                    iii=0;while(iii < location.args.length){
                        if( location.args[iii] === eachLink.args[ii] ){
                            matchArgs++;
                            break; // Only one match necessary.
                        }
                    iii++;}
                ii++;}
                // ALL of the link's args must match the location...
                if( matchArgs !== eachLink.args.length ){ continue; }
            }
            eachLink.className += ' active';
            eachLink.style.fontStyle = 'italic';
        }
    }
    // Done walking links.
}

function getElementsByClass(searchClass,node,tag){
    var classElements = new Array();
    if(node === null){ node = document; }
    if(tag === null){ tag = '*'; }
    var els = node.getElementsByTagName(tag);
    var elsLen = els.length;
    var pattern = new RegExp("(^|\\s)"+searchClass+"(\\s|$)");
    for (i = 0, j = 0; i < elsLen; i++) {
        if ( pattern.test(els[i].className) ) {
          classElements[j] = els[i];
          j++;
        }
    }
    return classElements;
}

if(typeof getStyle === 'undefined'){
    function getStyle(el, cssProp){ // Modified & tested by Eric.
        var returnValue;
        if(el.currentStyle){ // IE
            if(cssProp === 'backgroundPosition'){
                returnValue = el.currentStyle.backgroundPositionX+' '+el.currentStyle.backgroundPositionY;
            }else{ returnValue = el.currentStyle[cssProp]; }
        }else if(document.defaultView && document.defaultView.getComputedStyle){ // FF
            if(cssProp === 'backgroundPositionX'){
                returnValue = document.defaultView.getComputedStyle(el,'').backgroundPosition.split(' ')[0];
            }else if(cssProp === 'backgroundPositionY'){
                returnValue = document.defaultView.getComputedStyle(el,'').backgroundPosition.split(' ')[1];
            }else{ returnValue = document.defaultView.getComputedStyle(el,'')[cssProp]; }
        }else{ // ?? Last ditch. Useless?
            returnValue = el.style[cssProp];
        }
        return returnValue;
    }
}

if(typeof XMLHttpRequest === 'undefined'){
    var XMLHttpRequest = function () {
        try { return new ActiveXObject('Msxml2.XMLHTTP.6.0'); }
            catch (e) {}
        try { return new ActiveXObject('Msxml2.XMLHTTP.3.0'); }
            catch (e) {}
        try { return new ActiveXObject('Msxml2.XMLHTTP'); }
            catch (e) {}
        throw new Error('Cant do AJAX.');
    }
}

var clock = new Object();
clock.monthNamesAll = new Array('January','February','March','April','May','June','July','August','September','October','November','December');
clock.dayNamesAll = new Array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
clock.daysInMonthsAll = new Array(31,28,31,30,31,30,31,31,30,31,30,31);
clock.props = new Array('year','year2','month','month2','monthName','monthShort','day','day2','dayName','dayShort','hours24','hours12','mins','secs','mills','tenths','amPM','ticToc');
clock.propsRegExp = "";
var i=clock.props.length;while(i--){
    clock.propsRegExp += clock.props[i];
    if(i){ clock.propsRegExp += "|"; }
}
clock.propsRegExp = new RegExp("_("+clock.propsRegExp+")_","gi");

clock.start = function(){
    if(document.readyState != 'complete'){ setTimeout('clock.start();', 10); return; }
    clock.clientTime = new Date().getTime();
    if(typeof serverTime == 'undefined'){
        clock.serverOffset = false; // No server time!
        alert('Clock says: Server time not sent! All times are local.');
    }else{
        serverTime *= 1000; // PHP's time is in seconds, JS in milliseconds.
        clock.serverOffset = serverTime - clock.clientTime;
        if(serverTime.leapYear){ clock.daysInAllMonths[1] = 29; }
    }
    clock.targets = new Array();
    clock.treeWalker();
    setInterval( "clock.update();", 249 );
};clock.start();

clock.update = function(){
    clock.clientDateObj = new Date( new Date().getTime() );
    if(clock.serverOffset !== false){ // Use time from server.
        clock.serverDateObj = new Date( new Date().getTime()+clock.serverOffset );
    }else{ // Duplicate time from client.
        clock.serverDateObj = clock.clientDateObj;
    } // Needs ability to run BOTH time sources.
    clock.dateObj = clock.serverDateObj;
    clock.year = clock.dateObj.getFullYear();
    clock.year2 = Number(String(clock.year).replace(/^.*(\d\d)$/gi,'$1'));
    clock.month = clock.dateObj.getMonth()+1;
    if(clock.month < 10){ clock.month2 = '0'+clock.month; }
    else{ clock.month2 = clock.month;  }
    clock.monthName = clock.monthNamesAll[clock.dateObj.getMonth()];
    clock.monthShort = clock.monthName.replace(/^(\w\w\w).*$/gi,'$1');
    clock.daysInMonth = clock.daysInMonthsAll[clock.dateObj.getMonth()];
    clock.day = clock.dateObj.getDate();
    if(clock.day < 10){ clock.day2 = '0'+clock.day; }
    else{ clock.day2 = clock.day;  }
    clock.dayName = clock.dayNamesAll[clock.dateObj.getDay()];
    clock.dayShort = clock.dayName.replace(/^(\w\w\w).*$/gi,'$1');
    clock.hours24 = Number( clock.dateObj.getHours() );
    clock.hours12 = Number( clock.dateObj.getHours() );
    if(clock.dateObj.getHours() < 12){
        clock.amPM = 'am';
        if(clock.dateObj.getHours() == 0){ clock.hours12 = 12; }
    }else{
        clock.amPM = 'pm';
        if(clock.dateObj.getHours() > 12){ clock.hours12 -= 12; }
    }
    clock.mins = Number( clock.dateObj.getMinutes() );
    clock.secs = Number( clock.dateObj.getSeconds() );
    clock.mills = Number( clock.dateObj.getMilliseconds() );
    clock.tenths = Number( String( clock.dateObj.getMilliseconds() ).replace(/^(.).*$/gi,'$1') );
    if(clock.hours24 < 10){ clock.hours24 = '0'+clock.hours24; }
    if(clock.mins < 10){ clock.mins = '0'+clock.mins; }
    if(clock.secs < 10){ clock.secs = '0'+clock.secs; }
    clock.tic = '<span style="visibility:visible;">:</span>';
    clock.toc = '<span style="visibility:hidden;">:</span>';
    clock.ticToc = clock.mills < 501 ? clock.tic : clock.toc; // 2hz.
    i=clock.targets.length;while(i--){
        clock.targets[i].innerHTML = clock[clock.targets[i].prop];
    }
};

clock.treeWalker = function(nodeBranch){
    if(!nodeBranch){ nodeBranch = document.body; }
    switch(nodeBranch.nodeType){
        case 1: // Element.
            if( nodeBranch.className.match(/editor_form/i) ){ break; }
            var i=nodeBranch.childNodes.length;while(i--){
                clock.treeWalker(nodeBranch.childNodes[i]);
            }
        break;
        case 3: // Text.
            var newElem = nodeBranch.nodeValue.match(clock.propsRegExp);
            if( !newElem ){ break; }
            
            newElem.pre = nodeBranch.nodeValue.indexOf(newElem[0]);
            newElem.pre = nodeBranch.nodeValue.substring(0,newElem.pre);
            newElem.pre = document.createTextNode(newElem.pre);
            
            newElem.post = nodeBranch.nodeValue.indexOf(newElem[0])+newElem[0].length;
            newElem.post = nodeBranch.nodeValue.substring(newElem.post);
            newElem.post = document.createTextNode(newElem.post);
            
            newElem.mid = document.createElement("span");
            newElem.mid.className = "clock "+newElem.prop;
            newElem.mid.prop = newElem[0].replace(/_(\w+)_/gi,"$1");
            
            nodeBranch.parentNode.insertBefore(newElem.pre,nodeBranch);
            nodeBranch.parentNode.insertBefore(newElem.mid,nodeBranch);
            nodeBranch.parentNode.replaceChild(newElem.post,nodeBranch);
            newElem.mid.parentNode.normalize();
            
            clock.targets.push(newElem.mid);
            clock.treeWalker(newElem.pre);
            clock.treeWalker(newElem.post);
        break;
    }
};