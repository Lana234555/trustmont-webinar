/* Google Sheet → Extensions → Apps Script: paste this, set SECRET, then Deploy → New deployment →
   Web app, Execute as: Me, Who has access: Anyone. Put the web app URL and SECRET into lead-config.php. */
var SECRET = 'change-me';
var HEAD = ['Дата (Киев)','Имя','Телефон','Email','Страна','utm_source','utm_medium','utm_campaign','utm_content','utm_term','Страница'];

function doPost(e) {
  var d = JSON.parse(e.postData.contents || '{}');
  if (SECRET && d.secret !== SECRET) return out({ok:false});
  var sh = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
  if (sh.getLastRow() === 0) sh.appendRow(HEAD);
  sh.appendRow([d.date, d.first_name, "'" + d.phone, d.email, d.country,
    d.utm_source, d.utm_medium, d.utm_campaign, d.utm_content, d.utm_term, d.page]);
  return out({ok:true});
}
function out(o) { return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
