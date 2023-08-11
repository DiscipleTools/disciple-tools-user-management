/**
 * Users table in LitElement
 */

jQuery(document).ready(function($) {
  $('#chart').empty().html(`
    <style>
        users-table {
            --sort-both: url(${window.wpApiShare.template_dir + '/dt-assets/images/sort_both.png'});
            --sort-desc: url(${window.wpApiShare.template_dir + '/dt-assets/images/sort_desc.png'});
            --sort-asc: url(${window.wpApiShare.template_dir + '/dt-assets/images/sort_asc.png'});
        }
    </style>
    <users-table></users-table>`)
})

import {html, css, LitElement, range, map, classMap, unsafeCSS} from 'https://cdn.jsdelivr.net/gh/lit/dist@2/all/lit-all.min.js';

export class UsersTable extends LitElement {
  constructor() {
    super();

    this.users = [{name:'loading...'}]
    this.getUsers();
  }

  async getUsers( search = '', sort = '' ) {
    let users =  await fetch(`/wp-json/user-management/v2/users?limit=500&search=${search}&sort=${sort}`, {
      headers: {
        'X-WP-Nonce': window.wpApiShare.nonce
      }
    }).then(res => res.json());
    this.users = users;
  }

  static properties = {
    users: {type: Array, state:true},
    search: {type: String, state:true},
    sort: {type: String, state:true}
  }

  static get styles() {
    return [
      css`
        table {
          font-family: arial, sans-serif;
          border-collapse: collapse;
          width: 100%;
        }
        
        td, th {
          border: 1px solid #dddddd;
          text-align: left;
          padding: 8px;
        }
        
        tr:nth-child(even) {
          background-color: #dddddd;
        }
        .sortable th {
          background-repeat: no-repeat;
          background-position: center right;
          padding-right:1.5rem;
          background-image: var(--sort-both);
        }
        .sortable .sorting_desc {
          background-image: var(--sort-desc);
        }
        .sortable .sorting_asc {
          background-image: var(--sort-asc);
        }
      `
    ]
  }

  open_edit_modal(){
    jQuery('#user_modal').foundation('open');
  }

  search_text(){
    this.search = this.shadowRoot.querySelector('#search-users').value;
    this.getUsers( this.search )
  }

  sort_column(e){
    let column = e.target.id;
    if ( this.sort === column ) {
      column = column.includes('-') ? column.replace('-', '') : '-' + column
    }
    this.getUsers( this.search, column )

    //add sort_asc or sort_desc class to column
    this.shadowRoot.querySelectorAll('th').forEach(th=> th.classList.remove('sorting_asc', 'sorting_desc'));
    e.target.classList.add(column.includes('-') ? 'sorting_desc' : 'sorting_asc');

    this.sort = column;
  }

  render() {
    return html`
        <h2>TABLE OF USERS</h2> 
        <input id="search-users" type="text" placeholder="search"><button @click="${this.search_text}">Go</button>
        <br>
        <table class="sortable">
            <tr>
                ${Object.keys(window.dt_users_table.fields).map(k=>{
                  if ( window.dt_users_table.fields[k].hidden === true  ) return;
                  return html`<th id="${k}" @click="${this.sort_column}">
                      ${window.dt_users_table.fields[k].label}
                  </th>`
                })}
            </tr>
            
            ${this.users.length && this.users.map(user => html`
                <tr data-user="${user.ID}" @click="${this.open_edit_modal}">
                    ${Object.keys(window.dt_users_table.fields).map(k=>{
                      if ( window.dt_users_table.fields[k].hidden === true  ) return;
                      let field = window.dt_users_table.fields[k];
                      
                      //text and number fields
                      if ( field.type === 'text' || field.type === 'number' ){
                        return html`<td>${user[k]}</td>`
                      } else 
                      //array fields   
                      if ( ['array', 'array_keys'].includes(field.type) ){
                        let labels = (user[k] || []).map(v=>{
                          return field.options[v]?.label || v
                        }).join(', ')
                        return html`<td>${labels}</td>`
                      } else
                      //key select fields  
                      if ( ['key_select'].includes(field.type)){
                        return html`<td>${field.options[user[k]]?.label || user[k]}</td>`
                      } else
                      //location grid fields  
                      if ( ['location_grid'].includes(field.type) ){
                        let labels = (user[k] || []).map(v=>{
                          return v.label || v
                        }).join(', ')
                        return html`<td>${labels}</td>`
                      }
                    })}
                </tr>
            `
            )}
        </table>
    `;
  }
}

customElements.define('users-table', UsersTable);