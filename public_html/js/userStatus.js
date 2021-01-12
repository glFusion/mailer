/**
 * Updates subscriber status and indicators.
 */
var MLRxmlHttp;

function MLR_toggleUserStatus(stat, id, base_url)
{
  MLRxmlHttp=MLRGetXmlHttpObject();
  if (MLRxmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }
  var url=base_url + "/ajax.php?action=userstatus";
  url=url+"&id="+id;
  url=url+"&newval="+stat;
  url=url+"&sid="+Math.random();
  MLRxmlHttp.onreadystatechange=MLRsc_toggleEnabled;
  MLRxmlHttp.open("GET",url,true);
  MLRxmlHttp.send(null);
}

function MLRsc_toggleEnabled()
{

  if (MLRxmlHttp.readyState==4 || MLRxmlHttp.readyState=="complete")
  {
    xmlDoc=MLRxmlHttp.responseXML;
    id = xmlDoc.getElementsByTagName("id")[0].childNodes[0].nodeValue;
    newstat = xmlDoc.getElementsByTagName("newstat")[0].childNodes[0].nodeValue;
    baseurl = xmlDoc.getElementsByTagName("baseurl")[0].childNodes[0].nodeValue;
    newicon1 = xmlDoc.getElementsByTagName("icon1")[0].childNodes[0].nodeValue;
    newicon2 = xmlDoc.getElementsByTagName("icon2")[0].childNodes[0].nodeValue;
    newicon3 = xmlDoc.getElementsByTagName("icon3")[0].childNodes[0].nodeValue;
    icon1 = "<img src=\"" + baseurl + "/images/" + newicon1 + "\"";
    icon2 = "<img src=\"" + baseurl + "/images/" + newicon2 + "\"";
    icon3 = "<img src=\"" + baseurl + "/images/" + newicon3 + "\"";

    if (newstat != 1) {
        icon1 = icon1 + "onclick='MLR_toggleUserStatus(\"1\", \"" + id + 
            "\", \"" + baseurl + "\")';";
    }
     if (newstat != 0) {
        icon2 = icon2 + "onclick='MLR_toggleUserStatus(\"0\", \"" + id + 
            "\", \"" + baseurl + "\")';";
    }
     if (newstat != 2) {
        icon3 = icon3 + "onclick='MLR_toggleUserStatus(\"2\", \"" + id + 
            "\", \"" + baseurl + "\")';";
    }
    icon1 = icon1 + " class=\"gl_mootip\" title=\"dummy\" />";
    icon2 = icon2 + " />";
    icon3 = icon3 + " />";
    
    document.getElementById("userstatus"+id).innerHTML = icon1 + icon2 + icon3;

    //icon1 = document.getElementById("icon1");
    //icon1.src = baseurl + "/images/" + icon1;
    //icon1.title = "this is a test";
        
  }

}

function MLRGetXmlHttpObject()
{
  var objXMLHttp=null
  if (window.XMLHttpRequest)
  {
    objXMLHttp=new XMLHttpRequest()
  }
  else if (window.ActiveXObject)
  {
    objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP")
  }
  return objXMLHttp
}

